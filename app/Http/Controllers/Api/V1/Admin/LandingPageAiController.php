<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\GenerateLandingContentRequest;
use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use App\Services\Ai\LandingAiGenerator;
use Illuminate\Http\JsonResponse;

class LandingPageAiController extends Controller
{
    public function generate(GenerateLandingContentRequest $request, LandingAiGenerator $ai): JsonResponse
    {
        if (! $ai->isConfigured()) {
            return response()->json(['message' => __('admin.import_ai_unavailable')], 422);
        }

        $data = $request->validated();

        $clinic   = isset($data['clinic_id'])   ? Clinic::with('city')->find($data['clinic_id']) : null;
        $city     = isset($data['city_id'])     ? City::find($data['city_id']) : null;
        $category = isset($data['category_id']) ? Category::find($data['category_id']) : null;

        try {
            $draft = $ai->generate($clinic, $data['service'] ?? null, $city, $category);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => __('admin.import_ai_failed')], 422);
        }

        // A draft for review — never persisted/published here.
        return response()->json(['data' => $draft]);
    }
}
