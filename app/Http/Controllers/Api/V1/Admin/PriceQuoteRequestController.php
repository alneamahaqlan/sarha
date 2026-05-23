<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StorePriceQuoteRequestRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePriceQuoteRequestRequest;
use App\Http\Resources\Api\V1\PriceQuoteRequestResource as PriceQuoteApiResource;
use App\Models\PriceQuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PriceQuoteRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PriceQuoteRequest::class);

        $query = PriceQuoteRequest::query()->with('clinic:id,name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('service_name', 'like', "%{$search}%")
                  ->orWhereHas('clinic', fn($qq) => $qq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }
        if ($clinicId = $request->input('filter.clinic_id')) {
            $query->where('clinic_id', $clinicId);
        }

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['created_at', 'status', 'customer_name'];
        if (in_array($column, $allowed, true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return PriceQuoteApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(PriceQuoteRequest $priceQuote): PriceQuoteApiResource
    {
        $this->authorize('view', $priceQuote);

        return new PriceQuoteApiResource($priceQuote->load('clinic:id,name'));
    }

    public function store(StorePriceQuoteRequestRequest $request): JsonResponse
    {
        $quote = PriceQuoteRequest::create($request->validated());

        return (new PriceQuoteApiResource($quote->load('clinic:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePriceQuoteRequestRequest $request, PriceQuoteRequest $priceQuote): PriceQuoteApiResource
    {
        $priceQuote->update($request->validated());

        return new PriceQuoteApiResource($priceQuote->fresh()->load('clinic:id,name'));
    }

    public function destroy(PriceQuoteRequest $priceQuote): JsonResponse
    {
        $this->authorize('delete', $priceQuote);

        $priceQuote->delete();

        return response()->json(null, 204);
    }
}
