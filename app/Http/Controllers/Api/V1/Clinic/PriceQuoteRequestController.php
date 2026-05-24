<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StoreQuoteReplyRequest;
use App\Http\Resources\Api\V1\QuoteRequestResource;
use App\Models\PriceQuoteReply;
use App\Models\PriceQuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Broadcast quote requests as seen by the authenticated complex: every request
 * targeting the complex's city, with the ability to post one reply (public or
 * private) per request.
 */
class PriceQuoteRequestController extends Controller
{
    private function clinic()
    {
        return auth('clinic')->user();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $clinic = $this->clinic();

        $query = PriceQuoteRequest::query()
            ->whereHas('cities', fn ($q) => $q->where('cities.id', $clinic->city_id))
            ->with([
                'cities:id,name,name_en',
                'replies' => fn ($q) => $q->where('clinic_id', $clinic->id),
            ])
            ->withCount('replies');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('service_name', 'like', "%{$search}%");
            });
        }

        // filter: replied = this clinic already replied; pending = not yet.
        $filter = $request->string('filter.status')->toString();
        if ($filter === 'replied') {
            $query->whereHas('replies', fn ($q) => $q->where('clinic_id', $clinic->id));
        } elseif ($filter === 'pending') {
            $query->whereDoesntHave('replies', fn ($q) => $q->where('clinic_id', $clinic->id));
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return QuoteRequestResource::collection($query->paginate($perPage)->withQueryString());
    }

    /** Create or update this clinic's reply to a broadcast request. */
    public function reply(StoreQuoteReplyRequest $request, PriceQuoteRequest $priceQuote): JsonResponse
    {
        $clinic = $this->clinic();

        // The complex may only reply to requests targeting its city.
        abort_unless(
            $priceQuote->cities()->where('cities.id', $clinic->city_id)->exists(),
            403,
        );

        $data = $request->validated();

        PriceQuoteReply::updateOrCreate(
            ['price_quote_request_id' => $priceQuote->id, 'clinic_id' => $clinic->id],
            [
                'body'      => $data['body'],
                'price'     => $data['price'] ?? null,
                'is_public' => (bool) ($data['is_public'] ?? false),
            ],
        );

        if ($priceQuote->status === 'new') {
            $priceQuote->update(['status' => 'replied']);
        }

        $priceQuote->load([
            'cities:id,name,name_en',
            'replies' => fn ($q) => $q->where('clinic_id', $clinic->id),
        ])->loadCount('replies');

        return (new QuoteRequestResource($priceQuote))->response();
    }
}
