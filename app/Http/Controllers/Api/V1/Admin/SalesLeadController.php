<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ConvertSalesLeadRequest;
use App\Http\Requests\Api\V1\Admin\StoreSalesLeadRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSalesLeadRequest;
use App\Http\Resources\Api\V1\ClinicResource as ClinicApiResource;
use App\Http\Resources\Api\V1\SalesLeadResource as SalesLeadApiResource;
use App\Models\SalesLead;
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

        $query = SalesLead::query()->with(['city:id,name', 'assignedAdmin:id,name']);

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

        return new SalesLeadApiResource($salesLead->load(['city:id,name', 'assignedAdmin:id,name']));
    }

    public function store(StoreSalesLeadRequest $request): JsonResponse
    {
        $lead = SalesLead::create($request->validated());

        return (new SalesLeadApiResource($lead->load(['city:id,name', 'assignedAdmin:id,name'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSalesLeadRequest $request, SalesLead $salesLead): SalesLeadApiResource
    {
        $salesLead->update($request->validated());

        return new SalesLeadApiResource($salesLead->fresh()->load(['city:id,name', 'assignedAdmin:id,name']));
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
                'lead'   => (new SalesLeadApiResource($salesLead->fresh()->load(['city:id,name', 'assignedAdmin:id,name'])))->toArray($request),
                'clinic' => (new ClinicApiResource($clinic))->toArray($request),
            ],
        ], 201);
    }
}
