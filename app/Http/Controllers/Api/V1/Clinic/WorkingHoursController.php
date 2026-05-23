<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\ClinicWorkingHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkingHoursController extends Controller
{
    private function clinicId(): int
    {
        return (int) auth('clinic')->id();
    }

    /**
     * Return the 7 working-hour rows for the authenticated clinic.
     * Missing days are seeded with sensible defaults (closed Friday).
     */
    public function index(): JsonResponse
    {
        $clinicId = $this->clinicId();

        $existing = ClinicWorkingHour::where('clinic_id', $clinicId)
            ->get()
            ->keyBy('day_of_week');

        $rows = [];
        foreach (array_keys(ClinicWorkingHour::DAYS) as $day) {
            $row = $existing->get($day);
            if (! $row) {
                $row = ClinicWorkingHour::create([
                    'clinic_id'   => $clinicId,
                    'day_of_week' => $day,
                    'is_open'     => $day !== 5, // Friday closed by default
                    'opens_at'    => '09:00',
                    'closes_at'   => '17:00',
                ]);
            }
            $rows[] = [
                'day_of_week' => (int) $row->day_of_week,
                'is_open'     => (bool) $row->is_open,
                'open_time'   => $row->opens_at,
                'close_time'  => $row->closes_at,
            ];
        }

        return response()->json(['data' => $rows]);
    }

    /**
     * Bulk upsert the authenticated clinic's working hours.
     */
    public function update(Request $request): JsonResponse
    {
        $clinicId = $this->clinicId();

        $validated = $request->validate([
            'hours'                => ['required', 'array', 'size:7'],
            'hours.*.day_of_week'  => ['required', 'integer', Rule::in(array_keys(ClinicWorkingHour::DAYS))],
            'hours.*.is_open'      => ['required', 'boolean'],
            'hours.*.open_time'    => ['nullable', 'date_format:H:i'],
            'hours.*.close_time'   => ['nullable', 'date_format:H:i'],
        ]);

        DB::transaction(function () use ($validated, $clinicId) {
            foreach ($validated['hours'] as $row) {
                ClinicWorkingHour::updateOrCreate(
                    ['clinic_id' => $clinicId, 'day_of_week' => $row['day_of_week']],
                    [
                        'is_open'   => $row['is_open'],
                        'opens_at'  => $row['open_time'] ?? null,
                        'closes_at' => $row['close_time'] ?? null,
                    ],
                );
            }
        });

        return $this->index();
    }
}
