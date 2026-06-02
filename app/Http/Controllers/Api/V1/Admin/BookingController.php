<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreBookingRequest;
use App\Http\Requests\Api\V1\Admin\UpdateBookingRequest;
use App\Http\Resources\Api\V1\BookingResource as BookingApiResource;
use App\Models\Booking;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Booking::class);

        $query = $this->filteredQuery($request)
            ->with(['clinic:id,name', 'service:id,name', 'relative', 'booker:id,name,phone']);

        $sort = $request->string('sort', '-created_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $allowed = ['created_at', 'customer_name', 'status', 'appointment_at', 'reference_code'];
        if (in_array($column, $allowed, true)) {
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return BookingApiResource::collection($query->paginate($perPage)->withQueryString());
    }

    /**
     * CSV export of bookings honouring the current table filters, an
     * optional status subset, and a date sort direction.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Booking::class);

        $query = $this->filteredQuery($request)->with(['clinic:id,name', 'service:id,name']);

        // Status subset (storage statuses). Empty = all.
        $statuses = array_filter((array) $request->input('statuses', []));
        if ($statuses) {
            $query->whereIn('status', $statuses);
        }

        $dir = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy('created_at', $dir);

        $headers = ['المرجع', 'المجمع', 'العميل', 'الجوال', 'الخدمة', 'الحالة', 'موعد الحجز', 'تاريخ الإنشاء', 'المصدر'];

        return CsvExport::streamQuery('bookings-' . now()->format('Y-m-d') . '.csv', $headers, $query,
            fn (Booking $b) => [
                $b->reference_code,
                $b->clinic?->name ?? '',
                $b->customer_name,
                $b->customer_phone,
                $b->service?->name ?? '',
                Booking::STATUS_LABELS_AR[$b->status] ?? $b->status,
                optional($b->appointment_at)->format('Y-m-d H:i') ?? '',
                optional($b->created_at)->format('Y-m-d H:i') ?? '',
                $b->source ?? '',
            ],
        );
    }

    /**
     * Shared query: applies trashed scope, search and the status/clinic
     * filters used by both the table listing and the CSV export.
     */
    private function filteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        // Mirror Filament's withoutGlobalScopes(SoftDeletingScope) — see BookingResource::getEloquentQuery().
        $query = Booking::query()->withoutGlobalScopes([SoftDeletingScope::class]);

        // TrashedFilter mapping: only|with|without (matches Filament default).
        $trashed = $request->string('filter.trashed')->toString();
        if ($trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($trashed === 'without' || $trashed === '') {
            $query->whereNull('deleted_at');
        }
        // 'with' => no additional clause; both trashed and non-trashed are returned.

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhereHas('clinic', fn($qq) => $qq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }
        if ($clinicId = $request->input('filter.clinic_id')) {
            $query->where('clinic_id', $clinicId);
        }
        if ($serviceId = $request->input('filter.service_id')) {
            $query->where('service_id', $serviceId);
        }
        // Sub-clinic has no direct column — constrain through the service.
        if ($subClinicId = $request->input('filter.sub_clinic_id')) {
            $query->whereHas('service', fn ($q) => $q->where('sub_clinic_id', $subClinicId));
        }

        return $query;
    }

    public function statusCounts(): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $statuses = ['new', 'contacted', 'appointment_set', 'completed', 'no_show', 'cancelled'];

        // Counts over non-trashed bookings (mirrors the default 'without' table scope).
        $byStatus = Booking::query()
            ->whereNull('deleted_at')
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

        return new BookingApiResource($booking->load(['clinic:id,name', 'service:id,name']));
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        // Booking::booted() handles reference_code generation; observer fires NotificationService.
        $booking = Booking::create($request->validated());

        return (new BookingApiResource($booking->load(['clinic:id,name', 'service:id,name'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateBookingRequest $request, Booking $booking): BookingApiResource
    {
        $booking->update($request->validated());

        return new BookingApiResource($booking->fresh()->load(['clinic:id,name', 'service:id,name']));
    }

    public function destroy(Booking $booking): JsonResponse
    {
        $this->authorize('delete', $booking);

        $booking->delete();

        return response()->json(null, 204);
    }

    public function restore(Booking $booking): BookingApiResource
    {
        $this->authorize('restore', $booking);

        $booking->restore();

        return new BookingApiResource($booking->fresh()->load(['clinic:id,name', 'service:id,name']));
    }

    public function forceDestroy(Booking $booking): JsonResponse
    {
        $this->authorize('forceDelete', $booking);

        $booking->forceDelete();

        return response()->json(null, 204);
    }
}
