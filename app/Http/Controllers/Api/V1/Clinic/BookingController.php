<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\UpdateBookingRequest;
use App\Http\Resources\Api\V1\BookingResource as BookingApiResource;
use App\Models\Booking;
use App\Services\ClinicActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function __construct(
        private readonly ClinicActivityLogger $activity,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Booking::class);

        $query = Booking::query()
            ->where('clinic_id', auth('clinic')->id())
            ->with(['service:id,name', 'relative', 'booker:id,name,phone']);

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

        return new BookingApiResource($booking->load(['service:id,name', 'relative', 'booker:id,name,phone']));
    }

    public function update(UpdateBookingRequest $request, Booking $booking): BookingApiResource
    {
        $oldStatus = $booking->status;
        $data = $request->validated();

        // Strip Kanban-only context keys before persisting — they
        // never live on the bookings table.
        $context = [
            'cancel_reason'  => $data['cancel_reason']  ?? null,
            'cancel_note'    => $data['cancel_note']    ?? null,
            'completion_note'=> $data['completion_note']?? null,
        ];
        unset($data['cancel_reason'], $data['cancel_note'], $data['completion_note']);

        $booking->update($data);

        $newStatus = $booking->status;
        if ($oldStatus !== $newStatus) {
            $summary = [
                'reference' => $booking->reference_code,
                'customer'  => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'from'      => $oldStatus,
                'to'        => $newStatus,
            ];
            if ($newStatus === 'cancelled') {
                if (! empty($context['cancel_reason'])) $summary['reason'] = $context['cancel_reason'];
                if (! empty($context['cancel_note']))   $summary['note']   = $context['cancel_note'];
            }
            if (in_array($newStatus, ['completed', 'no_show'], true) && ! empty($context['completion_note'])) {
                $summary['note'] = $context['completion_note'];
            }
            $this->activity->log("booking.{$newStatus}", $booking, $summary);
        }

        return new BookingApiResource($booking->fresh()->load(['service:id,name', 'relative', 'booker:id,name,phone']));
    }
}
