<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\UpdatePriceQuoteRequest;
use App\Http\Resources\Api\V1\PriceQuoteRequestResource as PriceQuoteApiResource;
use App\Models\PriceQuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PriceQuoteRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PriceQuoteRequest::class);

        $query = PriceQuoteRequest::query()->where('clinic_id', auth('clinic')->id());

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('service_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return PriceQuoteApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(PriceQuoteRequest $priceQuote): PriceQuoteApiResource
    {
        $this->authorize('view', $priceQuote);

        return new PriceQuoteApiResource($priceQuote);
    }

    public function update(UpdatePriceQuoteRequest $request, PriceQuoteRequest $priceQuote): PriceQuoteApiResource
    {
        $priceQuote->update($request->validated());

        return new PriceQuoteApiResource($priceQuote->fresh());
    }
}
