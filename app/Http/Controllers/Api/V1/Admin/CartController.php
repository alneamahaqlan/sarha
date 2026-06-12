<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super-admin control surface for the per-clinic cart feature:
 *   - the activation request queue (approve / reject)
 *   - per-clinic enable / disable
 *
 * Mirrors the tracking moderation controller. The `sales` admin role is
 * excluded — feature gating is a super_admin / admin concern.
 */
class CartController extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_if(auth('admin')->user()?->role === 'sales', 403, 'غير مصرّح.');
    }

    /** Moderation queue. ?status=pending (default) | active | all */
    public function requests(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $status = $request->string('status')->toString() ?: 'pending';

        $query = Clinic::query()
            ->select(['id', 'name', 'slug', 'cart_status', 'cart_rejection_reason', 'cart_requested_at'])
            ->when($status !== 'all', fn ($q) => $q->where('cart_status', $status))
            ->when($status === 'all', fn ($q) => $q->whereIn('cart_status', ['pending', 'active', 'rejected']))
            ->orderByRaw("FIELD(cart_status, 'pending', 'active', 'rejected', 'disabled')")
            ->orderByDesc('cart_requested_at');

        return response()->json(['data' => $query->get()]);
    }

    /** Approve a request or enable a clinic outright → active. */
    public function approve(Clinic $clinic): JsonResponse
    {
        $this->authorizeAdmin();

        $clinic->update([
            'cart_status'           => 'active',
            'cart_rejection_reason' => null,
            'cart_reviewed_by'      => auth('admin')->id(),
        ]);

        return response()->json(['data' => $clinic->only(['id', 'cart_status'])]);
    }

    public function reject(Request $request, Clinic $clinic): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $clinic->update([
            'cart_status'           => 'rejected',
            'cart_rejection_reason' => $data['reason'] ?? null,
            'cart_reviewed_by'      => auth('admin')->id(),
        ]);

        return response()->json(['data' => $clinic->only(['id', 'cart_status'])]);
    }

    /** Disable the cart for a clinic immediately. */
    public function disable(Clinic $clinic): JsonResponse
    {
        $this->authorizeAdmin();

        $clinic->update([
            'cart_status'      => 'disabled',
            'cart_reviewed_by' => auth('admin')->id(),
        ]);

        return response()->json(['data' => $clinic->only(['id', 'cart_status'])]);
    }
}
