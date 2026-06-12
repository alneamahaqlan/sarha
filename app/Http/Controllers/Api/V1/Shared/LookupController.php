<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\BeforeAfterPhoto;
use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Offer;
use App\Models\Service;
use App\Models\SubClinic;
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

    /** Sub-clinics of a given complex — powers cascading booking filters. */
    public function subClinics(Request $request): JsonResponse
    {
        $clinicId = (int) $request->input('clinic_id');
        if (! $clinicId) {
            return response()->json(['data' => []]);
        }

        $q = SubClinic::query()->select(['id', 'name'])->where('clinic_id', $clinicId);

        if ($search = $request->string('search')->toString()) {
            $q->where('name', 'like', "%{$search}%");
        }

        return response()->json(['data' => $q->orderBy('name')->limit(100)->get()]);
    }

    /** Services of a given complex — powers cascading booking filters. */
    public function services(Request $request): JsonResponse
    {
        $clinicId = (int) $request->input('clinic_id');
        if (! $clinicId) {
            return response()->json(['data' => []]);
        }

        $q = Service::query()->select(['id', 'name'])->where('clinic_id', $clinicId);

        if ($search = $request->string('search')->toString()) {
            $q->where('name', 'like', "%{$search}%");
        }

        return response()->json(['data' => $q->orderBy('name')->limit(100)->get()]);
    }

    /** Offers of a given complex — for landing-page manual block selection. */
    public function offers(Request $request): JsonResponse
    {
        $clinicId = (int) $request->input('clinic_id');
        if (! $clinicId) {
            return response()->json(['data' => []]);
        }

        $q = Offer::query()->select(['id', 'title'])->where('clinic_id', $clinicId);

        if ($search = $request->string('search')->toString()) {
            $q->where('title', 'like', "%{$search}%");
        }

        return response()->json([
            'data' => $q->orderByDesc('is_featured')->orderByDesc('starts_at')->limit(100)
                ->get()->map(fn ($o) => ['id' => $o->id, 'name' => $o->title]),
        ]);
    }

    /** Doctors of a given complex — for landing-page manual block selection. */
    public function doctors(Request $request): JsonResponse
    {
        $clinicId = (int) $request->input('clinic_id');
        if (! $clinicId) {
            return response()->json(['data' => []]);
        }

        $q = Doctor::query()->select(['id', 'name'])->where('clinic_id', $clinicId);

        if ($search = $request->string('search')->toString()) {
            $q->where('name', 'like', "%{$search}%");
        }

        return response()->json(['data' => $q->orderBy('sort_order')->orderBy('name')->limit(100)->get()]);
    }

    /** Before/after cases of a given complex — for landing-page manual gallery. */
    public function beforeAfter(Request $request): JsonResponse
    {
        $clinicId = (int) $request->input('clinic_id');
        if (! $clinicId) {
            return response()->json(['data' => []]);
        }

        $rows = BeforeAfterPhoto::query()->select(['id', 'title'])->where('clinic_id', $clinicId)
            ->orderBy('sort_order')->limit(100)->get();

        return response()->json([
            'data' => $rows->map(fn ($r, $i) => ['id' => $r->id, 'name' => $r->title ?: ('#' . ($i + 1))]),
        ]);
    }

    public function admins(Request $request): JsonResponse
    {
        $q = Admin::query()->select(['id', 'name', 'role'])->where('is_active', true);

        if ($role = $request->string('role')->toString()) {
            $roles = array_filter(array_map('trim', explode(',', $role)));
            if ($roles) {
                $q->whereIn('role', $roles);
            }
        }

        if ($search = $request->string('search')->toString()) {
            $q->where('name', 'like', "%{$search}%");
        }

        return response()->json(['data' => $q->orderBy('name')->limit(50)->get()]);
    }
}
