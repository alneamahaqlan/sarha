<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\CreateBookingRequest;
use App\Http\Requests\Api\V1\Clinic\UpdateBookingRequest;
use App\Http\Resources\Api\V1\BookingResource as BookingApiResource;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicTeamMember;
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

    public function store(CreateBookingRequest $request): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();
        $data = $request->validated();

        // Walk-in / phone booking — source flags this row as
        // clinic-originated (vs. customer-originated from /book).
        $payload = [
            'clinic_id'      => $clinicId,
            'customer_name'  => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'service_id'     => $data['service_id'] ?? null,
            'appointment_at' => $data['appointment_at'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'clinic_notes'   => $data['clinic_notes'] ?? null,
            'status'         => $data['status'] ?? 'new',
            'source'         => 'clinic',
            'acquisition_source' => $data['acquisition_source'] ?? 'other',
        ];

        // Validate + map assignee (the picker only sends valid types).
        if (! empty($data['assignee_type']) && ! empty($data['assignee_id'])) {
            $fq = $data['assignee_type'] === 'Clinic' ? Clinic::class : ClinicTeamMember::class;
            $ok = $fq === Clinic::class
                ? (int) $data['assignee_id'] === $clinicId
                : ClinicTeamMember::where('id', $data['assignee_id'])
                    ->where('clinic_id', $clinicId)
                    ->where('is_active', true)
                    ->exists();
            if ($ok) {
                $payload['assignee_type'] = $fq;
                $payload['assignee_id']   = (int) $data['assignee_id'];
            }
        }

        $booking = Booking::create($payload);

        $this->activity->log('booking.created_manually', $booking, [
            'reference' => $booking->reference_code,
            'customer'  => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
        ]);

        return response()->json([
            'data' => (new BookingApiResource($booking->fresh()->load(['service:id,name']))),
        ], 201);
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

        // Keep stage and status consistent: when the status changes (e.g.
        // via the edit dialog, not a drag) and the booking's current stage
        // no longer matches the new status's kind, detach the stage so the
        // board falls back to that kind's primary stage. A drag sends a
        // matching stage_id + status together, so this is a no-op there.
        if (! array_key_exists('stage_id', $data) && $booking->stage_id) {
            $stage = $booking->stage()->first();
            if ($stage && $stage->kind !== Booking::kindForStatus($booking->status)) {
                $booking->update(['stage_id' => null]);
            }
        }

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
