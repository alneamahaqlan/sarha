<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\CategoryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

/** A complex proposes a new specialty (category); admins review it. */
class CategoryRequestController extends Controller
{
    private function clinicId(): int
    {
        return (int) auth('clinic')->id();
    }

    public function index(): JsonResponse
    {
        $rows = CategoryRequest::query()
            ->where('clinic_id', $this->clinicId())
            ->latest()
            ->get(['id', 'name', 'status', 'created_at']);

        return response()->json(['data' => $rows]);
    }

    public function store(): JsonResponse
    {
        $clinicId = $this->clinicId();

        $data = request()->validate([
            'name' => [
                'required', 'string', 'max:255',
                // No duplicate pending request with the same name from this complex.
                Rule::unique('category_requests', 'name')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId)->where('status', 'pending')),
            ],
        ]);

        $req = CategoryRequest::create([
            'clinic_id' => $clinicId,
            'name'      => $data['name'],
            'status'    => 'pending',
        ]);

        return response()->json([
            'data'    => ['id' => $req->id, 'name' => $req->name, 'status' => $req->status],
            'message' => __('admin.category_requests.submitted'),
        ], 201);
    }
}
