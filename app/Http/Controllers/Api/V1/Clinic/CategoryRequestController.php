<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CategoryRequest;
use App\Models\PlatformNotification;
use App\Models\Service;
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
            ->get(['id', 'name', 'status', 'service_id', 'created_at']);

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
            // Optional — if the request came from inside a service form, we
            // record the service so the admin's approval auto-attaches the
            // new specialty back to it. Must belong to the authenticated clinic.
            'service_id' => [
                'nullable', 'integer',
                Rule::exists('services', 'id')->where('clinic_id', $clinicId),
            ],
        ]);

        $req = CategoryRequest::create([
            'clinic_id'  => $clinicId,
            'service_id' => $data['service_id'] ?? null,
            'name'       => $data['name'],
            'status'     => 'pending',
        ]);

        // Notify every active admin so the review queue isn't missed. Same
        // platform_notifications mechanism the booking/complaint flows use.
        $clinicName = optional(auth('clinic')->user())->name ?? '—';
        foreach (Admin::where('is_active', true)->get(['id']) as $admin) {
            PlatformNotification::create([
                'notifiable_type' => Admin::class,
                'notifiable_id'   => $admin->id,
                'type'            => 'category_request.new',
                'icon'            => 'heroicon-o-tag',
                'url'             => '/app/admin/category-requests',
                'priority'        => 'normal',
                'title'           => 'طلب تخصص جديد',
                'body'            => "{$clinicName} اقترح تخصص: {$req->name}",
                'data'            => ['category_request_id' => $req->id, 'service_id' => $req->service_id],
            ]);
        }

        return response()->json([
            'data'    => ['id' => $req->id, 'name' => $req->name, 'status' => $req->status, 'service_id' => $req->service_id],
            'message' => __('admin.category_requests.submitted'),
        ], 201);
    }
}
