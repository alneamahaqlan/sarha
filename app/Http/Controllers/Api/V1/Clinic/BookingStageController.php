<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-clinic Kanban stage labels. Only the displayed names of the 4
 * fixed columns can be customised — the underlying workflow statuses
 * (and the auto-tags/suggestions that depend on them) never change.
 */
class BookingStageController extends Controller
{
    /** The renamable Kanban columns (keys of Booking::KANBAN_GROUPS). */
    private const COLUMNS = ['new', 'confirmed', 'completed', 'cancelled'];

    public function show(): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);
        $clinic = Clinic::findOrFail((int) auth('clinic')->id());

        return response()->json(['data' => (object) ($clinic->booking_stage_labels ?? [])]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);
        $clinic = Clinic::findOrFail((int) auth('clinic')->id());

        $data = $request->validate([
            'labels'           => ['required', 'array'],
            'labels.new'       => ['nullable', 'string', 'max:40'],
            'labels.confirmed' => ['nullable', 'string', 'max:40'],
            'labels.completed' => ['nullable', 'string', 'max:40'],
            'labels.cancelled' => ['nullable', 'string', 'max:40'],
        ]);

        // Keep only known columns; trim and drop empties so blank fields
        // fall back to the default translated label on the client.
        $labels = collect($data['labels'])
            ->only(self::COLUMNS)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->all();

        $clinic->booking_stage_labels = $labels ?: null;
        $clinic->save();

        return response()->json(['data' => (object) ($clinic->booking_stage_labels ?? [])]);
    }
}
