<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BookingDetailResource;
use App\Http\Resources\Api\V1\BookingKanbanCardResource;
use App\Models\Booking;
use App\Services\BookingKanbanService;
use App\Services\CustomerInsightService;
use App\Support\ActingClinicUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingKanbanController extends Controller
{
    public function __construct(
        private readonly BookingKanbanService $kanban,
        private readonly CustomerInsightService $insights,
        private readonly \App\Services\BookingStageService $stageService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);
        $clinicId = (int) auth('clinic')->id();

        $filters = $this->resolveFilters($request);
        $stages  = $this->stageService->ensureDefaults($clinicId);
        $primary = $this->stageService->primaryStageIds($stages);

        // Board is keyed by stage id (string). The frontend zips this
        // with the stages list (GET bookings/stages) for column metadata.
        $payload = [];
        foreach ($stages as $stage) {
            $key       = (string) $stage->id;
            $isPrimary = ($primary[$stage->kind] ?? null) === $stage->id;

            $cursor = $request->input("cursors.{$key}");
            $cursor = $cursor !== null && $cursor !== '' ? (int) $cursor : null;
            $result = $this->kanban->columnForStage($clinicId, $stage, $isPrimary, $filters, $cursor, 20);

            $payload[$key] = [
                'items'       => BookingKanbanCardResource::collection($result['items'])->toArray($request),
                'next_cursor' => $result['next_cursor'],
                'has_more'    => $result['has_more'],
                'total'       => $this->kanban->baseQuery($clinicId, $filters)->forStage($stage, $isPrimary)->count(),
                // Total net value of all cards in this stage — sum of the
                // services' net_price over the bookings in the column.
                'value_total' => (float) \App\Models\BookingService::query()
                    ->whereIn('booking_id', $this->kanban->baseQuery($clinicId, $filters)
                        ->forStage($stage, $isPrimary)
                        ->select('bookings.id'))
                    ->sum('net_price'),
            ];
        }

        return response()->json(['data' => $payload]);
    }

    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);
        $clinicId = (int) auth('clinic')->id();
        return response()->json(['data' => $this->kanban->stats($clinicId)]);
    }

    public function tagLabels(): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);
        $clinicId = (int) auth('clinic')->id();
        return response()->json(['data' => $this->kanban->tagLabels($clinicId)]);
    }

    public function show(Booking $booking): BookingDetailResource
    {
        $this->authorize('view', $booking);
        $booking->load(['service:id,name,price', 'services.service:id,name,price', 'booker:id,name,phone', 'relative', 'assignee', 'tags', 'customer:id,follow_up_priority']);
        $booking->loadCount('services');
        return new BookingDetailResource($booking);
    }

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
            'acquisition_source' => $request->input('acquisition_source'),
            'mine_only'     => filter_var($request->input('mine_only'), FILTER_VALIDATE_BOOLEAN),
        ];

        if ($filters['mine_only']) {
            $filters['actor_type'] = ActingClinicUser::actorType();
            $filters['actor_id']   = ActingClinicUser::actorId();
        }

        return $filters;
    }
}
