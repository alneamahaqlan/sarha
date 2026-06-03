<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingKanbanService;
use App\Support\ActingClinicUser;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of the clinic's bookings. Honours the same filters as the
 * Kanban board, an optional stage subset, and a date sort direction.
 */
class BookingExportController extends Controller
{
    public function __construct(private readonly BookingKanbanService $kanban) {}

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Booking::class);
        $clinicId = (int) auth('clinic')->id();

        $query = $this->kanban->baseQuery($clinicId, $this->resolveFilters($request))
            // Re-declare service eager load with sub_clinic_id so we can
            // print the sub-clinic name column.
            ->with(['service:id,name,sub_clinic_id', 'service.subClinic:id,name']);

        // Stage subset: a list of Kanban columns. Empty = all stages.
        $columns = array_filter((array) $request->input('columns', []));
        if ($columns) {
            $statuses = collect($columns)
                ->flatMap(fn ($c) => Booking::KANBAN_GROUPS[$c] ?? [])
                ->unique()
                ->all();
            $query->whereIn('status', $statuses ?: ['__none__']);
        }

        $dir = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy('created_at', $dir);

        $headers = ['المرجع', 'العميل', 'الجوال', 'الخدمة', 'العيادة الفرعية', 'الحالة', 'موعد الحجز', 'تاريخ الإنشاء', 'المصدر'];

        return CsvExport::streamQuery('bookings-' . now()->format('Y-m-d') . '.csv', $headers, $query,
            fn (Booking $b) => [
                $b->reference_code,
                $b->customer_name,
                $b->customer_phone,
                $b->service?->name ?? '',
                $b->service?->subClinic?->name ?? '',
                Booking::STATUS_LABELS_AR[$b->status] ?? $b->status,
                optional($b->appointment_at)->format('Y-m-d H:i') ?? '',
                optional($b->created_at)->format('Y-m-d H:i') ?? '',
                $b->source ?? '',
            ],
        );
    }

    /** Mirrors BookingKanbanController::resolveFilters. */
    private function resolveFilters(Request $request): array
    {
        $filters = [
            'search'        => $request->string('search')->toString(),
            'service_id'    => $request->input('service_id'),
            'sub_clinic_id' => $request->input('sub_clinic_id'),
            'assignee_id'   => $request->input('assignee_id'),
            'assignee_type' => $request->input('assignee_type'),
            'date_from'     => $request->input('date_from'),
            'date_to'       => $request->input('date_to'),
            'auto_tag'      => $request->input('auto_tag'),
            'custom_tag'    => $request->input('custom_tag'),
            'mine_only'     => filter_var($request->input('mine_only'), FILTER_VALIDATE_BOOLEAN),
        ];

        if ($filters['mine_only']) {
            $filters['actor_type'] = ActingClinicUser::actorType();
            $filters['actor_id']   = ActingClinicUser::actorId();
        }

        return $filters;
    }
}
