<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\RejectComplaintRequest;
use App\Http\Requests\Api\V1\Admin\ResolveComplaintRequest;
use App\Http\Requests\Api\V1\Admin\StoreComplaintRequest;
use App\Http\Requests\Api\V1\Admin\UpdateComplaintRequest;
use App\Http\Resources\Api\V1\ComplaintResource as ComplaintApiResource;
use App\Models\Complaint;
use App\Services\ComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComplaintController extends Controller
{
    public function __construct(private readonly ComplaintService $complaints) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Complaint::class);

        $query = Complaint::query()->with(['clinic:id,name', 'assignedAdmin:id,name']);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        foreach (['status', 'type', 'priority', 'clinic_id', 'source'] as $f) {
            if ($val = $request->input("filter.{$f}")) {
                $query->where($f, $val);
            }
        }

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['created_at', 'reference_code', 'priority', 'status'];
        if (in_array($column, $allowed, true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return ComplaintApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(Complaint $complaint): ComplaintApiResource
    {
        $this->authorize('view', $complaint);

        return new ComplaintApiResource($complaint->load(['clinic:id,name', 'assignedAdmin:id,name']));
    }

    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $complaint = Complaint::create($request->validated());

        return (new ComplaintApiResource($complaint->load(['clinic:id,name', 'assignedAdmin:id,name'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateComplaintRequest $request, Complaint $complaint): ComplaintApiResource
    {
        $complaint->update($request->validated());

        return new ComplaintApiResource($complaint->fresh()->load(['clinic:id,name', 'assignedAdmin:id,name']));
    }

    public function destroy(Complaint $complaint): JsonResponse
    {
        $this->authorize('delete', $complaint);

        $complaint->delete();

        return response()->json(null, 204);
    }

    // ---- Action endpoints (delegate to ComplaintService — same code Filament calls). ----

    public function markInReview(Complaint $complaint): ComplaintApiResource
    {
        $this->authorize('markInReview', $complaint);

        return new ComplaintApiResource(
            $this->complaints->markInReview($complaint)->load(['clinic:id,name', 'assignedAdmin:id,name'])
        );
    }

    public function resolve(ResolveComplaintRequest $request, Complaint $complaint): ComplaintApiResource
    {
        return new ComplaintApiResource(
            $this->complaints->resolve($complaint, $request->validated('resolution'))
                ->load(['clinic:id,name', 'assignedAdmin:id,name'])
        );
    }

    public function reject(RejectComplaintRequest $request, Complaint $complaint): ComplaintApiResource
    {
        return new ComplaintApiResource(
            $this->complaints->reject($complaint, $request->validated('admin_notes'))
                ->load(['clinic:id,name', 'assignedAdmin:id,name'])
        );
    }

    public function notifyClinic(Complaint $complaint): ComplaintApiResource
    {
        $this->authorize('notifyClinic', $complaint);

        return new ComplaintApiResource(
            $this->complaints->notifyClinic($complaint)->load(['clinic:id,name', 'assignedAdmin:id,name'])
        );
    }
}
