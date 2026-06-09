<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CampaignRecipient;
use App\Models\ClinicCampaign;
use App\Support\CsvExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin intake queue for "managed" campaign requests — campaigns a clinic
 * prepared (image + text + audience) and asked the platform to run. The
 * platform runs them OUTSIDE the system; this controller only lists the
 * requests, exposes the recipient data (CSV), and lets staff close a
 * handled item. No sending happens here.
 */
class CampaignRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ClinicCampaign::query()
            ->where('type', ClinicCampaign::TYPE_MANAGED)
            ->with('clinic:id,name')
            ->latest();

        // filter[status] = submitted | closed (omit / 'all' = no filter)
        $status = $request->string('filter.status')->toString();
        if ($status && $status !== 'all') {
            $query->where('managed_status', $status);
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $page = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (ClinicCampaign $c) => $this->row($c)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
                'from'         => $page->firstItem(),
                'to'           => $page->lastItem(),
            ],
        ]);
    }

    public function show(ClinicCampaign $campaign): JsonResponse
    {
        $this->ensureManaged($campaign);
        $campaign->load('clinic:id,name', 'closedByAdmin:id,name');

        return response()->json([
            'data' => $this->row($campaign) + [
                'message_template' => $campaign->message_template,
                'audience'         => $campaign->audience,
                'closed_by'        => $campaign->closedByAdmin?->name,
                'closed_at'        => $campaign->managed_closed_at?->toIso8601String(),
            ],
        ]);
    }

    /** Stream the recipient list (name + phone) so staff can run it externally. */
    public function export(ClinicCampaign $campaign): StreamedResponse
    {
        $this->ensureManaged($campaign);

        $labels = [
            CampaignRecipient::STATUS_PENDING => 'بانتظار',
            CampaignRecipient::STATUS_SENT    => 'أُرسل',
            CampaignRecipient::STATUS_SKIPPED => 'تخطّي',
        ];

        return CsvExport::streamQuery(
            'campaign-' . $campaign->id . '-' . now()->format('Y-m-d') . '.csv',
            ['الاسم', 'الجوال', 'الحالة'],
            $campaign->recipients()->orderBy('id'),
            fn (CampaignRecipient $r) => [
                $r->name_snapshot ?? '—',
                $r->phone_snapshot ?? '—',
                $labels[$r->status] ?? $r->status,
            ],
        );
    }

    /** Mark the request handled so it leaves the active queue. */
    public function close(ClinicCampaign $campaign): JsonResponse
    {
        $this->ensureManaged($campaign);
        abort_unless($campaign->managed_status === ClinicCampaign::MANAGED_SUBMITTED, 422, 'الطلب مُغلق مسبقاً.');

        $campaign->update([
            'managed_status'             => ClinicCampaign::MANAGED_CLOSED,
            'managed_closed_at'          => now(),
            'managed_closed_by_admin_id' => (int) auth('admin')->id(),
        ]);

        return response()->json(['message' => 'تم إغلاق الطلب.']);
    }

    // ---------- internal ----------

    private function ensureManaged(ClinicCampaign $campaign): void
    {
        abort_unless($campaign->isManaged(), 404);
    }

    private function row(ClinicCampaign $c): array
    {
        return [
            'id'               => $c->id,
            'name'             => $c->name,
            'managed_status'   => $c->managed_status,
            'total_recipients' => (int) $c->total_recipients,
            'image_url'        => $c->image_path ? Storage::url($c->image_path) : null,
            'clinic'           => $c->clinic ? ['id' => $c->clinic->id, 'name' => $c->clinic->name] : null,
            'created_at'       => $c->created_at?->toIso8601String(),
        ];
    }
}
