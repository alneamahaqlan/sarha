<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ConvertSalesLeadRequest;
use App\Http\Requests\Api\V1\Admin\StoreSalesLeadActivityRequest;
use App\Http\Requests\Api\V1\Admin\StoreSalesLeadRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSalesLeadRequest;
use App\Http\Resources\Api\V1\ClinicResource as ClinicApiResource;
use App\Http\Resources\Api\V1\SalesLeadActivityResource;
use App\Http\Resources\Api\V1\SalesLeadResource as SalesLeadApiResource;
use App\Models\SalesLead;
use App\Models\SalesLeadActivity;
use App\Models\SubscriptionPackage;
use App\Services\SalesLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SalesLeadController extends Controller
{
    public function __construct(private readonly SalesLeadService $leads) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesLead::class);

        $query = SalesLead::query()
            ->with(['city:id,name', 'assignedAdmin:id,name'])
            ->withCount('activities');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('clinic_name', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        $sort = $request->string('sort', 'next_follow_up_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['clinic_name', 'status', 'created_at', 'next_follow_up_at', 'last_contact_at'];
        if (in_array($column, $allowed, true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('next_follow_up_at');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return SalesLeadApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(SalesLead $salesLead): SalesLeadApiResource
    {
        $this->authorize('view', $salesLead);

        return new SalesLeadApiResource(
            $salesLead->loadCount('activities')->load(['city:id,name', 'assignedAdmin:id,name'])
        );
    }

    public function store(StoreSalesLeadRequest $request): JsonResponse
    {
        $lead = SalesLead::create($request->validated());

        // Seed the timeline with the lead's opening status.
        $this->logActivity($lead, SalesLeadActivity::TYPE_STATUS_CHANGE, null, [
            'from' => null,
            'to'   => $lead->status,
        ]);

        return (new SalesLeadApiResource($lead->loadCount('activities')->load(['city:id,name', 'assignedAdmin:id,name'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSalesLeadRequest $request, SalesLead $salesLead): SalesLeadApiResource
    {
        $validated = $request->validated();

        $oldStatus     = $salesLead->status;
        $oldFollowUp   = $salesLead->next_follow_up_at?->toIso8601String();

        $salesLead->update($validated);

        // Rescheduling the follow-up re-arms the overdue reminder.
        if (array_key_exists('next_follow_up_at', $validated)
            && ($salesLead->next_follow_up_at?->toIso8601String()) !== $oldFollowUp) {
            $salesLead->forceFill(['followup_notified_at' => null])->save();
        }

        // A status transition is logged to the timeline automatically.
        if (array_key_exists('status', $validated) && $validated['status'] !== $oldStatus) {
            $this->logActivity($salesLead, SalesLeadActivity::TYPE_STATUS_CHANGE, null, [
                'from' => $oldStatus,
                'to'   => $salesLead->status,
            ]);
        }

        return new SalesLeadApiResource(
            $salesLead->fresh()->loadCount('activities')->load(['city:id,name', 'assignedAdmin:id,name'])
        );
    }

    public function destroy(SalesLead $salesLead): JsonResponse
    {
        $this->authorize('delete', $salesLead);

        $salesLead->delete();

        return response()->json(null, 204);
    }

    public function convert(ConvertSalesLeadRequest $request, SalesLead $salesLead): JsonResponse
    {
        // Authorization handled in ConvertSalesLeadRequest via SalesLeadPolicy@convert.
        $package = SubscriptionPackage::findOrFail($request->validated('package_id'));
        $amount  = $request->validated('amount');

        $clinic = $this->leads->convertLead(
            $salesLead,
            $package,
            $request->validated('billing_cycle'),
            $amount !== null ? (float) $amount : null,
        );

        return response()->json([
            'data' => [
                'lead'   => (new SalesLeadApiResource($salesLead->fresh()->loadCount('activities')->load(['city:id,name', 'assignedAdmin:id,name'])))->toArray($request),
                'clinic' => (new ClinicApiResource($clinic))->toArray($request),
            ],
        ], 201);
    }

    // ─────────────────────────── activities ───────────────────────────

    /** The lead's timeline (manual outreach + automatic status changes). */
    public function activities(SalesLead $salesLead): JsonResponse
    {
        $this->authorize('view', $salesLead);

        $items = $salesLead->activities()->limit(100)->get();

        return response()->json([
            'data' => SalesLeadActivityResource::collection($items)->resolve(),
        ]);
    }

    public function storeActivity(StoreSalesLeadActivityRequest $request, SalesLead $salesLead): JsonResponse
    {
        $validated = $request->validated();

        $activity = $this->logActivity($salesLead, $validated['type'], $validated['body'] ?? null);

        // A real outreach (call/whatsapp/email/meeting) advances last_contact_at.
        if (in_array($validated['type'], SalesLeadActivity::CONTACT_TYPES, true)) {
            $salesLead->forceFill(['last_contact_at' => now()])->save();
        }

        return response()->json([
            'data' => (new SalesLeadActivityResource($activity))->resolve(),
        ], 201);
    }

    /** Write one timeline row, snapshotting the acting admin's name. */
    private function logActivity(SalesLead $lead, string $type, ?string $body = null, ?array $meta = null): SalesLeadActivity
    {
        $admin = auth('admin')->user();

        return $lead->activities()->create([
            'admin_id'   => $admin?->id,
            'admin_name' => $admin?->name,
            'type'       => $type,
            'body'       => $body,
            'meta'       => $meta,
        ]);
    }
}
