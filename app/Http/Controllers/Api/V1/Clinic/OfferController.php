<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StoreOfferRequest;
use App\Http\Requests\Api\V1\Clinic\UpdateOfferRequest;
use App\Http\Resources\Api\V1\OfferResource;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Clinic-scoped offer CRUD. Mirrors the same scoping discipline as the
 * other clinic resource controllers — every read and write filters by
 * auth('clinic')->id().
 *
 * Filter query: ?status=active|scheduled|expired|paused — applied in PHP
 * (not SQL) because the bucket is a derived rule combining is_active +
 * dates and we want the single source of truth in Offer::status().
 */
class OfferController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $clinicId = (int) auth('clinic')->id();

        $offers = Offer::where('clinic_id', $clinicId)
            ->with('service:id,name,image,price')
            ->orderByDesc('is_featured')
            ->orderByDesc('starts_at')
            ->get();

        if ($status = $request->string('status')->toString()) {
            $offers = $offers->filter(fn (Offer $o) => $o->status() === $status)->values();
        }

        return OfferResource::collection($offers);
    }

    public function store(StoreOfferRequest $request): OfferResource
    {
        $clinicId = (int) auth('clinic')->id();

        $offer = Offer::create($request->validated() + ['clinic_id' => $clinicId]);

        return new OfferResource($offer->load('service:id,name,image,price'));
    }

    public function update(UpdateOfferRequest $request, Offer $offer): OfferResource
    {
        $this->authorizeOffer($offer);

        $offer->update($request->validated());

        return new OfferResource($offer->fresh()->load('service:id,name,image,price'));
    }

    public function destroy(Offer $offer): JsonResponse
    {
        $this->authorizeOffer($offer);

        $offer->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * Quick-action: bump ends_at by N days from its current value. Used
     * by the "تمديد" button on each card so the admin doesn't have to
     * open the full edit dialog for the most common operation.
     */
    public function extend(Request $request, Offer $offer): OfferResource
    {
        $this->authorizeOffer($offer);

        $days = (int) $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ])['days'];

        // If the offer already expired, extend from today; otherwise from
        // its current ends_at so the admin gets predictable +N days.
        $base = $offer->ends_at?->isPast() ? now() : $offer->ends_at;
        $offer->update(['ends_at' => $base->copy()->addDays($days), 'is_active' => true]);

        return new OfferResource($offer->fresh()->load('service:id,name,image,price'));
    }

    private function authorizeOffer(Offer $offer): void
    {
        abort_if($offer->clinic_id !== (int) auth('clinic')->id(), 404);
    }
}
