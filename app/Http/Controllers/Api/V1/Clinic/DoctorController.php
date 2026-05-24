<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StoreDoctorRequest;
use App\Http\Requests\Api\V1\Clinic\UpdateDoctorRequest;
use App\Http\Resources\Api\V1\DoctorResource;
use App\Models\Doctor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Doctors showcase managed by the authenticated complex. */
class DoctorController extends Controller
{
    private function clinicId(): int
    {
        return (int) auth('clinic')->id();
    }

    private function authorizeOwnership(Doctor $doctor): void
    {
        abort_if($doctor->clinic_id !== $this->clinicId(), 404);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Doctor::query()->where('clinic_id', $this->clinicId())->with('subClinic:id,name');

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('sort_order')->orderBy('name');

        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        return DoctorResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreDoctorRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['clinic_id'] = $this->clinicId();

        $doctor = Doctor::create($data);

        return (new DoctorResource($doctor->load('subClinic:id,name')))->response()->setStatusCode(201);
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor): DoctorResource
    {
        $this->authorizeOwnership($doctor);

        $doctor->update($request->validated());

        return new DoctorResource($doctor->fresh()->load('subClinic:id,name'));
    }

    public function destroy(Doctor $doctor): JsonResponse
    {
        $this->authorizeOwnership($doctor);

        $doctor->delete();

        return response()->json(null, 204);
    }
}
