<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lightweight read-only lookup endpoints used to populate Select dropdowns
 * in the React UI. Mirrors Filament's relationship('clinic', 'name'), etc.
 * Each list returns up to 50 rows with search support.
 */
class LookupController extends Controller
{
    public function clinics(Request $request): JsonResponse
    {
        $q = Clinic::query()->select(['id', 'name']);

        if ($search = $request->string('search')->toString()) {
            $q->where('name', 'like', "%{$search}%");
        }

        return response()->json(['data' => $q->orderBy('name')->limit(50)->get()]);
    }

    public function cities(Request $request): JsonResponse
    {
        $q = City::query()->select(['id', 'name', 'name_en'])->where('is_active', true);

        if ($search = $request->string('search')->toString()) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        return response()->json(['data' => $q->orderBy('sort_order')->orderBy('name')->limit(100)->get()]);
    }

    public function categories(Request $request): JsonResponse
    {
        $q = Category::query()->select(['id', 'name', 'name_en', 'slug', 'emoji'])->where('is_active', true);

        if ($search = $request->string('search')->toString()) {
            $q->where('name', 'like', "%{$search}%");
        }

        return response()->json(['data' => $q->orderBy('sort_order')->orderBy('name')->limit(100)->get()]);
    }
}
