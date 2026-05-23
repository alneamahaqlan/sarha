<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\UpdateBookingRequest;
use App\Http\Resources\Api\V1\BookingResource as BookingApiResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Booking::class);

        $query = Booking::query()
            ->where('clinic_id', auth('clinic')->id())
            ->with('service:id,name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return BookingApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function statusCounts(): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $statuses = ['new', 'contacted', 'appointment_set', 'completed', 'no_show', 'cancelled'];

        $byStatus = Booking::query()
            ->where('clinic_id', auth('clinic')->id())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = ['all' => (int) $byStatus->sum()];
        foreach ($statuses as $status) {
            $counts[$status] = (int) ($byStatus[$status] ?? 0);
        }

        return response()->json(['data' => $counts]);
    }

    public function show(Booking $booking): BookingApiResource
    {
        $this->authorize('view', $booking);

        return new BookingApiResource($booking->load('service:id,name'));
    }

    public function update(UpdateBookingRequest $request, Booking $booking): BookingApiResource
    {
        $booking->update($request->validated());

        return new BookingApiResource($booking->fresh()->load('service:id,name'));
    }
}
