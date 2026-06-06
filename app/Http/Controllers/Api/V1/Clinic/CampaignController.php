<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StoreCampaignRequest;
use App\Http\Resources\Api\V1\CampaignRecipientResource;
use App\Http\Resources\Api\V1\ClinicCampaignResource;
use App\Models\CampaignRecipient;
use App\Models\ClinicCampaign;
use App\Models\Customer;
use App\Services\ClinicActivityLogger;
use App\Services\CustomerSegmentFilter;
use App\Support\ActingClinicUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Patient re-engagement campaigns (v1 — manual per-recipient WhatsApp +
 * tracking). Audience is resolved through CustomerSegmentFilter so the
 * recipient count matches the Customers Hub list, and marketing opt-outs
 * are always excluded.
 *
 * Auth (gated at the route): campaigns.view for reads, campaigns.manage
 * for create / mark / delete.
 */
class CampaignController extends Controller
{
    /** Hard cap on a single campaign's audience — keeps inserts bounded. */
    private const MAX_RECIPIENTS = 1000;

    public function __construct(private readonly ClinicActivityLogger $activity) {}

    public function index(): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();

        $campaigns = ClinicCampaign::forClinic($clinicId)->latest()->limit(100)->get();

        return response()->json([
            'data' => ClinicCampaignResource::collection($campaigns)->resolve(),
        ]);
    }

    /** Live audience size for the create dialog (opt-outs + phoneless excluded). */
    public function preview(Request $request): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();
        $criteria = CustomerSegmentFilter::criteriaFrom((array) $request->input('audience', []));

        $count = $this->audienceQuery($clinicId, $criteria)->count();

        return response()->json(['data' => ['count' => $count]]);
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $clinicId  = (int) auth('clinic')->id();
        $validated = $request->validated();
        $criteria  = CustomerSegmentFilter::criteriaFrom($validated['audience'] ?? []);

        $customers = $this->audienceQuery($clinicId, $criteria)
            ->limit(self::MAX_RECIPIENTS)
            ->get(['id', 'name', 'phone']);

        $campaign = ClinicCampaign::create([
            'clinic_id'        => $clinicId,
            'name'             => trim($validated['name']),
            'message_template' => trim($validated['message_template']),
            'audience'         => $criteria,
            'status'           => ClinicCampaign::STATUS_ACTIVE,
            'total_recipients' => $customers->count(),
            'sent_count'       => 0,
            'created_by_type'  => ActingClinicUser::actorType(),
            'created_by_id'    => ActingClinicUser::actorId(),
            'created_by_name'  => ActingClinicUser::actorName() ?? '—',
        ]);

        // Snapshot the resolved audience as recipient rows.
        $now = now();
        $rows = $customers->map(fn (Customer $c) => [
            'campaign_id'    => $campaign->id,
            'customer_id'    => $c->id,
            'name_snapshot'  => $c->name,
            'phone_snapshot' => $c->phone,
            'status'         => CampaignRecipient::STATUS_PENDING,
            'created_at'     => $now,
            'updated_at'     => $now,
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            CampaignRecipient::insert($chunk);
        }

        $this->activity->log('campaign.created', $campaign, [
            'name'       => $campaign->name,
            'recipients' => $campaign->total_recipients,
        ]);

        return response()->json([
            'data' => (new ClinicCampaignResource($campaign))->resolve(),
        ], 201);
    }

    public function show(ClinicCampaign $campaign): JsonResponse
    {
        $this->ensureOwns($campaign);

        return response()->json([
            'data' => (new ClinicCampaignResource($campaign))->resolve(),
        ]);
    }

    public function recipients(ClinicCampaign $campaign, Request $request): JsonResponse
    {
        $this->ensureOwns($campaign);

        $query = $campaign->recipients()->orderBy('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $recipients = $query->limit(1000)->get();

        return response()->json([
            'data' => CampaignRecipientResource::collection($recipients)->resolve(),
        ]);
    }

    /** Flip a recipient between pending / sent / skipped and resync the counter. */
    public function markRecipient(ClinicCampaign $campaign, CampaignRecipient $recipient, Request $request): JsonResponse
    {
        $this->ensureOwns($campaign);
        abort_unless($recipient->campaign_id === $campaign->id, 404);

        $status = (string) $request->input('status');
        abort_unless(in_array($status, [
            CampaignRecipient::STATUS_PENDING,
            CampaignRecipient::STATUS_SENT,
            CampaignRecipient::STATUS_SKIPPED,
        ], true), 422);

        $recipient->update([
            'status'  => $status,
            'sent_at' => $status === CampaignRecipient::STATUS_SENT ? now() : null,
        ]);

        // Recompute sent_count from the source of truth; mark the campaign
        // completed once nothing is left pending.
        $sent    = $campaign->recipients()->where('status', CampaignRecipient::STATUS_SENT)->count();
        $pending = $campaign->recipients()->where('status', CampaignRecipient::STATUS_PENDING)->count();
        $campaign->update([
            'sent_count' => $sent,
            'status'     => $pending === 0 ? ClinicCampaign::STATUS_COMPLETED : ClinicCampaign::STATUS_ACTIVE,
        ]);

        return response()->json([
            'data' => [
                'recipient' => (new CampaignRecipientResource($recipient))->resolve(),
                'campaign'  => (new ClinicCampaignResource($campaign->fresh()))->resolve(),
            ],
        ]);
    }

    public function destroy(ClinicCampaign $campaign): JsonResponse
    {
        $this->ensureOwns($campaign);
        $campaign->delete();

        return response()->json(['data' => ['removed' => true]]);
    }

    // ---------- internal ----------

    /**
     * The campaign-eligible audience: clinic customers matching the criteria,
     * with a phone number, who haven't opted out of marketing.
     */
    private function audienceQuery(int $clinicId, array $criteria)
    {
        $q = Customer::query()
            ->where('clinic_id', $clinicId)
            ->where('marketing_opt_out', false)
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        return CustomerSegmentFilter::apply($q, $criteria);
    }

    private function ensureOwns(ClinicCampaign $campaign): void
    {
        abort_unless($campaign->clinic_id === (int) auth('clinic')->id(), 404);
    }
}
