<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Services\ClinicStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "My stats" data for the authenticated clinic. Accepts a custom from/to range
 * or a quick period (7/14/30/90). Always scoped to the logged-in clinic.
 */
class StatsController extends Controller
{
    public function index(Request $request, ClinicStatsService $stats): JsonResponse
    {
        $clinic = auth('clinic')->user();

        [$from, $to] = ClinicStatsService::resolveRange(
            $request->query('from'),
            $request->query('to'),
            (int) $request->query('period', 0),
        );

        // showAi=false — clinic-facing surface omits the AI source row
        // from the breakdown but still includes its count in the total.
        return response()->json(['data' => $stats->compute($clinic, $from, $to, showAi: false)]);
    }
}
