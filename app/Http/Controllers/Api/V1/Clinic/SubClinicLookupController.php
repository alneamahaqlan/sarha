<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\SubClinic;
use Illuminate\Http\JsonResponse;

class SubClinicLookupController extends Controller
{
    public function index(): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();

        $rows = SubClinic::query()
            ->select(['id', 'name'])
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rows]);
    }
}
