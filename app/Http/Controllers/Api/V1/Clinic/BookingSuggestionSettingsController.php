<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-clinic config for the Kanban card suggestions (the smart next-step
 * nudges). Each suggestion can be toggled on/off and the three
 * time/count-based ones retuned. The underlying booking statuses and
 * auto-tags are untouched — this only governs which nudges surface and
 * at what thresholds. See CustomerInsightService for where it's applied.
 */
class BookingSuggestionSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);
        $clinic = $this->clinic();

        return response()->json([
            'data'     => $clinic->suggestionSettings(),
            'defaults' => Clinic::SUGGESTION_DEFAULTS,
            'bounds'   => Clinic::SUGGESTION_BOUNDS,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize('create', Booking::class);
        $clinic = $this->clinic();

        $rules = [];
        foreach (Clinic::SUGGESTION_DEFAULTS as $key => $defaults) {
            $rules["{$key}"]          = ['sometimes', 'array'];
            $rules["{$key}.enabled"]  = ['sometimes', 'boolean'];
            foreach (['hours', 'count'] as $field) {
                if (! isset($defaults[$field])) {
                    continue;
                }
                [$min, $max] = Clinic::SUGGESTION_BOUNDS[$key][$field];
                $rules["{$key}.{$field}"] = ['sometimes', 'integer', "min:{$min}", "max:{$max}"];
            }
        }

        $validated = $request->validate($rules);

        // Persist only recognised keys/fields (drops anything unexpected),
        // then re-read through the accessor so the response is normalized
        // + clamped exactly like every other consumer sees it.
        $clean = [];
        foreach (Clinic::SUGGESTION_DEFAULTS as $key => $defaults) {
            if (! isset($validated[$key]) || ! is_array($validated[$key])) {
                continue;
            }
            $row = [];
            if (array_key_exists('enabled', $validated[$key])) {
                $row['enabled'] = (bool) $validated[$key]['enabled'];
            }
            foreach (['hours', 'count'] as $field) {
                if (isset($defaults[$field]) && isset($validated[$key][$field])) {
                    $row[$field] = (int) $validated[$key][$field];
                }
            }
            if ($row) {
                $clean[$key] = $row;
            }
        }

        $clinic->update(['suggestion_settings' => $clean]);

        return response()->json(['data' => $clinic->fresh()->suggestionSettings()]);
    }

    private function clinic(): Clinic
    {
        /** @var Clinic $clinic */
        $clinic = auth('clinic')->user();
        return $clinic;
    }
}
