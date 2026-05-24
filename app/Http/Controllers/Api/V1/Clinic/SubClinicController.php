<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\ReorderRequest;
use App\Http\Requests\Api\V1\Clinic\StoreSubClinicRequest;
use App\Http\Requests\Api\V1\Clinic\UpdateSubClinicRequest;
use App\Http\Resources\Api\V1\SubClinicResource;
use App\Models\SubClinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Manage the clinics (sub-clinics) inside the authenticated complex.
 * This is the canonical middle level: complex -> clinic -> service.
 */
class SubClinicController extends Controller
{
    private function clinicId(): int
    {
        return (int) auth('clinic')->id();
    }

    /** Ensure the bound sub-clinic belongs to the authenticated complex. */
    private function authorizeOwnership(SubClinic $subClinic): void
    {
        abort_if($subClinic->clinic_id !== $this->clinicId(), 404);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SubClinic::query()
            ->where('clinic_id', $this->clinicId())
            ->withCount('allServices');

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('sort_order')->orderBy('name');

        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        return SubClinicResource::collection($query->paginate($perPage)->withQueryString());
    }

    /** Lightweight id+name list for the service form dropdown. */
    public function lookup(): JsonResponse
    {
        $rows = SubClinic::query()
            ->select(['id', 'name'])
            ->where('clinic_id', $this->clinicId())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(StoreSubClinicRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['clinic_id'] = $this->clinicId();

        $subClinic = SubClinic::create($data);

        return (new SubClinicResource($subClinic->loadCount('allServices')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSubClinicRequest $request, SubClinic $subClinic): SubClinicResource
    {
        $this->authorizeOwnership($subClinic);

        $subClinic->update($request->validated());

        return new SubClinicResource($subClinic->fresh()->loadCount('allServices'));
    }

    public function destroy(SubClinic $subClinic): JsonResponse
    {
        $this->authorizeOwnership($subClinic);

        if ($subClinic->allServices()->exists()) {
            return response()->json(['message' => __('admin.validation.sub_clinic_has_services')], 422);
        }

        $subClinic->delete();

        return response()->json(null, 204);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $clinicId = $this->clinicId();

        DB::transaction(function () use ($request, $clinicId) {
            foreach ($request->validated()['order'] as $row) {
                SubClinic::where('id', $row['id'])
                    ->where('clinic_id', $clinicId)
                    ->update(['sort_order' => $row['sort_order']]);
            }
        });

        return response()->json(['message' => 'Reordered.']);
    }
}
