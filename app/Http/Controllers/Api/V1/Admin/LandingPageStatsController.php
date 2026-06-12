<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Services\LandingPageStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandingPageStatsController extends Controller
{
    public function show(Request $request, LandingPage $landingPage, LandingPageStatsService $stats): JsonResponse
    {
        $this->authorize('view', $landingPage);

        return response()->json([
            'data' => $stats->compute(
                $landingPage,
                $request->string('from')->toString() ?: null,
                $request->string('to')->toString() ?: null,
            ),
        ]);
    }
}
