<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super-admin control surface for the per-clinic price-quote REPLY gate.
 * Mirrors the cart moderation controller, but the feature ships 'active' by
 * default — so the common admin action here is `disable` (block a clinic),
 * plus approve/reject for clinics that ask to be re-enabled.
 *
 * The `sales` admin role is excluded — feature gating is a super_admin concern.
 */
class PriceQuoteAccessController extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_if(auth('admin')->user()?->role === 'sales', 403, 'غير مصرّح.');
    }

    /** Moderation queue. ?status=active (default) | pending | disabled | all, optional ?search= */
    public function requests(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $status = $request->string('status')->toString() ?: 'active';
        $search = $request->string('search')->toString();

        $query = Clinic::query()
            ->select(['id', 'name', 'slug', 'price_quote_status', 'price_quote_rejection_reason', 'price_quote_requested_at'])
            ->when($status !== 'all', fn ($q) => $q->where('price_quote_status', $status))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderByRaw("FIELD(price_quote_status, 'pending', 'active', 'disabled', 'rejected')")
            ->orderByDesc('price_quote_requested_at')
            ->orderBy('name')
            ->limit(200);

        return response()->json(['data' => $query->get()]);
    }

    /** Approve a request or enable a clinic outright → active. */
    public function approve(Clinic $clinic): JsonResponse
    {
        $this->authorizeAdmin();

        $clinic->update([
            'price_quote_status'           => 'active',
            'price_quote_rejection_reason' => null,
            'price_quote_reviewed_by'      => auth('admin')->id(),
        ]);

        return response()->json(['data' => $clinic->only(['id', 'price_quote_status'])]);
    }

    public function reject(Request $request, Clinic $clinic): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $clinic->update([
            'price_quote_status'           => 'rejected',
            'price_quote_rejection_reason' => $data['reason'] ?? null,
            'price_quote_reviewed_by'      => auth('admin')->id(),
        ]);

        return response()->json(['data' => $clinic->only(['id', 'price_quote_status'])]);
    }

    /** Block a clinic from replying (it keeps read access to requests). */
    public function disable(Clinic $clinic): JsonResponse
    {
        $this->authorizeAdmin();

        $clinic->update([
            'price_quote_status'      => 'disabled',
            'price_quote_reviewed_by' => auth('admin')->id(),
        ]);

        return response()->json(['data' => $clinic->only(['id', 'price_quote_status'])]);
    }
}
