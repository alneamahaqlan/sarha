<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages the service line items taken on a booking (Kanban card). A
 * card can hold many services; each carries a net_price (catalog price
 * minus any per-customer discount). The card's value = sum(net_price).
 */
class BookingServiceController extends Controller
{
    public function store(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'net_price'  => ['required', 'numeric', 'min:0'],
        ]);

        $service = Service::where('clinic_id', $booking->clinic_id)
            ->findOrFail($validated['service_id']);

        $line = BookingService::create([
            'clinic_id'  => $booking->clinic_id,
            'booking_id' => $booking->id,
            'service_id' => $service->id,
            'list_price' => $service->price,
            'net_price'  => $validated['net_price'],
        ]);

        // Keep the legacy "primary service" populated for back-compat
        // (filters / exports still read bookings.service_id).
        if (! $booking->service_id) {
            $booking->forceFill(['service_id' => $service->id])->save();
        }

        return response()->json(['data' => $this->present($line, $service->name)], 201);
    }

    public function update(Request $request, Booking $booking, BookingService $bookingService): JsonResponse
    {
        $this->authorize('update', $booking);
        abort_unless($bookingService->booking_id === $booking->id, 404);

        $validated = $request->validate([
            'net_price' => ['required', 'numeric', 'min:0'],
        ]);

        $bookingService->update(['net_price' => $validated['net_price']]);
        $bookingService->loadMissing('service:id,name');

        return response()->json(['data' => $this->present($bookingService, $bookingService->service?->name)]);
    }

    public function destroy(Booking $booking, BookingService $bookingService): JsonResponse
    {
        $this->authorize('update', $booking);
        abort_unless($bookingService->booking_id === $booking->id, 404);

        $bookingService->delete();

        return response()->json(['data' => ['removed' => true]]);
    }

    private function present(BookingService $line, ?string $serviceName): array
    {
        return [
            'id'           => $line->id,
            'service_id'   => $line->service_id,
            'service_name' => $serviceName,
            'list_price'   => $line->list_price !== null ? (float) $line->list_price : null,
            'net_price'    => (float) $line->net_price,
        ];
    }
}
