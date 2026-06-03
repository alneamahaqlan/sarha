<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StorePackageRequest;
use App\Http\Requests\Api\V1\Clinic\UpdatePackageRequest;
use App\Http\Resources\Api\V1\PackageResource;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Packages (bundles of services across sub-clinics) managed by the complex. */
class PackageController extends Controller
{
    private function clinicId(): int
    {
        return (int) auth('clinic')->id();
    }

    private function authorizeOwnership(Package $package): void
    {
        abort_if($package->clinic_id !== $this->clinicId(), 404);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Package::query()
            ->where('clinic_id', $this->clinicId())
            ->with('services:id,name,sub_clinic_id');

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('sort_order')->orderBy('name');

        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        return PackageResource::collection($query->paginate($perPage)->withQueryString());
    }

    /**
     * Build the belongsToMany sync map — each service id mapped to its
     * pivot note, so the note rides along with the attachment.
     */
    private function syncMap(array $serviceIds, array $notes): array
    {
        return collect($serviceIds)
            ->mapWithKeys(fn ($id) => [(int) $id => ['note' => $notes[$id] ?? $notes[(string) $id] ?? null]])
            ->all();
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $serviceIds = $data['service_ids'] ?? [];
        $notes = $data['service_notes'] ?? [];
        unset($data['service_ids'], $data['service_notes']);
        $data['clinic_id'] = $this->clinicId();

        $package = Package::create($data);
        $package->services()->sync($this->syncMap($serviceIds, $notes));

        return (new PackageResource($package->load('services:id,name,sub_clinic_id')))
            ->response()->setStatusCode(201);
    }

    public function update(UpdatePackageRequest $request, Package $package): PackageResource
    {
        $this->authorizeOwnership($package);

        $data = $request->validated();
        $serviceIds = $data['service_ids'] ?? null;
        $notes = $data['service_notes'] ?? [];
        unset($data['service_ids'], $data['service_notes']);

        $package->update($data);
        if ($serviceIds !== null) {
            $package->services()->sync($this->syncMap($serviceIds, $notes));
        }

        return new PackageResource($package->fresh()->load('services:id,name,sub_clinic_id'));
    }

    public function destroy(Package $package): JsonResponse
    {
        $this->authorizeOwnership($package);

        $package->delete();

        return response()->json(null, 204);
    }
}
