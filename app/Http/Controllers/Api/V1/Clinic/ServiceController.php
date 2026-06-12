<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\ReorderRequest;
use App\Http\Requests\Api\V1\Clinic\StoreServiceRequest;
use App\Http\Requests\Api\V1\Clinic\UpdateServiceRequest;
use App\Http\Resources\Api\V1\ServiceResource as ServiceApiResource;
use App\Models\Offer;
use App\Models\Service;
use App\Services\CatalogServiceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function __construct(private readonly CatalogServiceResolver $catalog) {}

    private function clinicId(): int
    {
        return (int) auth('clinic')->id();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Service::class);

        $query = Service::query()
            ->with('categories:id,name,name_en,slug,emoji', 'catalogService:id,name,status', 'doctors:id,name', 'inlineOffer')
            ->where('clinic_id', $this->clinicId());

        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }
        if (! is_null($request->input('filter.is_active'))) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        $sort = $request->string('sort', 'sort_order')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['sort_order', 'name', 'price', 'is_active', 'created_at'];
        $query->orderBy(in_array($column, $allowed, true) ? $column : 'sort_order', $direction);

        $perPage = min(max((int) $request->input('per_page', 30), 1), 100);

        return ServiceApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $clinicId = $this->clinicId();
        $data['clinic_id'] = $clinicId;
        $categoryIds = $data['category_ids'] ?? [];
        $doctorIds   = $data['doctor_ids'] ?? [];
        $offerPrice  = $data['offer_price'] ?? null;
        $offerEndsAt = $data['offer_ends_at'] ?? null;
        unset($data['category_ids'], $data['doctor_ids'], $data['offer_price'], $data['offer_ends_at']);

        // Unified-catalog resolution: link to an existing canonical service
        // (→ approved, shows immediately) or file a new catalog request
        // (→ pending, hidden until an admin approves). An explicit
        // catalog_service_id from the picker skips matching.
        $resolution = $this->catalog->resolveForCreate(
            clinicId: $clinicId,
            name: $data['name'],
            explicitCatalogServiceId: $data['catalog_service_id'] ?? null,
            categoryIds: $categoryIds,
        );
        $data['catalog_service_id'] = $resolution['catalog_service_id'];
        $data['approval_status'] = $resolution['approval_status'];

        $service = Service::create($data);
        $service->categories()->sync($categoryIds);
        $service->doctors()->sync($doctorIds);
        $this->syncInlineOffer($service, $offerPrice, $offerEndsAt);

        return (new ServiceApiResource($service->load('categories:id,name,name_en,slug,emoji', 'catalogService:id,name,status', 'doctors:id,name', 'inlineOffer')))
            ->response()->setStatusCode(201)
            ->header('X-Catalog-Status', $resolution['approval_status']);
    }

    public function update(UpdateServiceRequest $request, Service $service): ServiceApiResource
    {
        // clinic_id never changes; ownership enforced in the request's authorize().
        $data = $request->validated();
        $categoryIds = $data['category_ids'] ?? null;
        // Sync doctors only when the key is present (partial updates). A present
        // key with an empty array clears all doctors.
        $hasDoctors  = $request->has('doctor_ids');
        $doctorIds   = $data['doctor_ids'] ?? [];
        $hasOffer    = $request->has('offer_price');
        $offerPrice  = $data['offer_price'] ?? null;
        $offerEndsAt = $data['offer_ends_at'] ?? null;
        unset($data['category_ids'], $data['doctor_ids'], $data['offer_price'], $data['offer_ends_at']);

        $service->update($data);
        if ($categoryIds !== null) {
            $service->categories()->sync($categoryIds);
        }
        if ($hasDoctors) {
            $service->doctors()->sync($doctorIds);
        }
        if ($hasOffer) {
            $this->syncInlineOffer($service->fresh(), $offerPrice, $offerEndsAt);
        }

        return new ServiceApiResource($service->fresh()->load('categories:id,name,name_en,slug,emoji', 'doctors:id,name', 'inlineOffer'));
    }

    /**
     * Upsert (or remove) the single offer backing the inline "سعر العرض"
     * field. A positive price below the service price creates/updates a real
     * service-type offer tagged origin=service_form; a null/empty/≥price
     * value removes it. Everything downstream (public offers section, status
     * badges, the Offers page) then treats it like any other offer.
     */
    private function syncInlineOffer(Service $service, $price, $endsAt): void
    {
        $existing = $service->inlineOffer; // null when none exists yet

        // Cleared, zero, or not actually a discount → drop the inline offer.
        if ($price === null || $price === '' || (float) $price <= 0 || (float) $price >= (float) $service->price) {
            $existing?->delete();
            return;
        }

        $ends = $endsAt
            ? Carbon::parse($endsAt)->endOfDay()
            : ($existing?->ends_at ?? now()->addDays(30));

        $payload = [
            'type'      => Offer::TYPE_SERVICE,
            'origin'    => Offer::ORIGIN_SERVICE_FORM,
            'title'     => $service->name,
            'old_price' => $service->price,
            'price'     => $price,
            'starts_at' => $existing?->starts_at ?? now(),
            'ends_at'   => $ends,
            'is_active' => true,
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            $service->offers()->create(array_merge($payload, ['clinic_id' => $service->clinic_id]));
        }
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $service->delete();

        return response()->json(null, 204);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $clinicId = $this->clinicId();

        DB::transaction(function () use ($request, $clinicId) {
            foreach ($request->validated()['order'] as $row) {
                // Scope every update to the authenticated clinic to prevent cross-clinic writes.
                Service::where('id', $row['id'])
                    ->where('clinic_id', $clinicId)
                    ->update(['sort_order' => $row['sort_order']]);
            }
        });

        return response()->json(['message' => 'Reordered.']);
    }
}
