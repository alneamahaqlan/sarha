<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClinicActivityLog;
use App\Services\ClinicActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Read + write activities on a single booking. All activity rows
 * land in the existing clinic_activity_logs table (subject_type =
 * Booking, subject_id = $booking->id) so they automatically appear
 * in the team-wide activity log already shipped with the team
 * feature.
 *
 * Quick-action writes never change Booking.status — per spec
 * confirmation the status transition is left to drag/drop.
 */
class BookingActivityController extends Controller
{
    public function __construct(
        private readonly ClinicActivityLogger $activity,
    ) {}

    private const QUICK_ACTIONS = [
        'call_attempted',
        'whatsapped',
        'reminder_sent',
        'patient_confirmed_verbally',
        'patient_cancelled_verbally',
        'note_added',
    ];

    public function index(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        $rows = ClinicActivityLog::query()
            ->where('clinic_id', $booking->clinic_id)
            ->where('model_type', Booking::class)
            ->where('model_id', $booking->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $rows->map(fn(ClinicActivityLog $r) => [
                'id'         => $r->id,
                'action'     => $r->action,
                'actor_name' => $r->actor_name,
                'actor_role' => $r->actor_role,
                'summary'    => $r->summary,
                'created_at' => Carbon::parse($r->created_at)->toIso8601String(),
            ])->all(),
        ]);
    }

    public function store(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        $validated = $request->validate([
            'action'  => ['required', 'string', Rule::in(self::QUICK_ACTIONS)],
            'note'    => ['nullable', 'string', 'max:1000'],
            'outcome' => ['nullable', 'string', Rule::in(['answered', 'no_answer'])],
        ]);

        $summary = [
            'reference' => $booking->reference_code,
            'customer'  => $booking->customer_name,
        ];
        if (! empty($validated['note']))    $summary['note']    = $validated['note'];
        if (! empty($validated['outcome'])) $summary['outcome'] = $validated['outcome'];

        $row = $this->activity->log("booking.{$validated['action']}", $booking, $summary);

        if (! $row) {
            return response()->json(['message' => 'log_unavailable'], 503);
        }

        return response()->json([
            'data' => [
                'id'         => $row->id,
                'action'     => $row->action,
                'actor_name' => $row->actor_name,
                'actor_role' => $row->actor_role,
                'summary'    => $row->summary,
                'created_at' => $row->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
