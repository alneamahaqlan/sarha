<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\PresentsCartItems;
use App\Http\Controllers\Controller;
use App\Models\CartContact;
use App\Models\CartItem;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;

/**
 * Super-admin read view over abandoned carts (unbooked cart items). The
 * index is a per-clinic roll-up — count of carts/items plus whether the
 * clinic has followed up in the last 3 days; the detail drills into one
 * clinic's customers and their items. Read-only: the clinic owns the
 * outreach. `sales` admin role excluded (mirrors CartController).
 */
class AbandonedCartController extends Controller
{
    use PresentsCartItems;

    /** Window (days) that counts as a "recent" clinic follow-up. */
    private const RECENT_DAYS = 3;

    private function authorizeAdmin(): void
    {
        abort_if(auth('admin')->user()?->role === 'sales', 403, 'غير مصرّح.');
    }

    /** Per-clinic abandoned-cart summary. */
    public function index(): JsonResponse
    {
        $this->authorizeAdmin();

        // One grouped pass over abandoned items → counts per clinic.
        $summaries = CartItem::abandoned()
            ->selectRaw('clinic_id, COUNT(*) as items_count, COUNT(DISTINCT user_id) as carts_count')
            ->groupBy('clinic_id')
            ->get()
            ->keyBy('clinic_id');

        if ($summaries->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $clinicIds = $summaries->keys()->all();

        // Latest clinic→customer outreach per clinic (for the "contacted recently" flag).
        $lastContacts = CartContact::whereIn('clinic_id', $clinicIds)
            ->selectRaw('clinic_id, MAX(created_at) as last_contacted_at')
            ->groupBy('clinic_id')
            ->pluck('last_contacted_at', 'clinic_id');

        $clinics = Clinic::whereIn('id', $clinicIds)->get(['id', 'name', 'slug'])->keyBy('id');

        $recentCutoff = now()->subDays(self::RECENT_DAYS);

        $data = $summaries->map(function ($row) use ($clinics, $lastContacts, $recentCutoff) {
            $clinic        = $clinics->get($row->clinic_id);
            $lastContacted = $lastContacts->get($row->clinic_id);
            $lastContactedAt = $lastContacted ? \Illuminate\Support\Carbon::parse($lastContacted) : null;

            return [
                'clinic'             => $clinic ? ['id' => $clinic->id, 'name' => $clinic->name, 'slug' => $clinic->slug] : null,
                'carts_count'        => (int) $row->carts_count,
                'items_count'        => (int) $row->items_count,
                'last_contacted_at'  => $lastContactedAt?->toIso8601String(),
                'contacted_recently' => $lastContactedAt ? $lastContactedAt->gte($recentCutoff) : false,
            ];
        })
            ->filter(fn ($r) => $r['clinic'] !== null)
            ->sortByDesc('items_count')
            ->values();

        return response()->json(['data' => $data]);
    }

    /** One clinic's abandoned carts grouped by customer. */
    public function show(Clinic $clinic): JsonResponse
    {
        $this->authorizeAdmin();

        $items = CartItem::abandoned()
            ->where('clinic_id', $clinic->id)
            ->with(['cartable', 'user:id,name,phone'])
            ->latest()
            ->get();

        $lastContacts = CartContact::where('clinic_id', $clinic->id)
            ->selectRaw('user_id, MAX(created_at) as last_contacted_at')
            ->groupBy('user_id')
            ->pluck('last_contacted_at', 'user_id');

        $recentCutoff = now()->subDays(self::RECENT_DAYS);

        $carts = $items->groupBy('user_id')->map(function ($userItems) use ($lastContacts, $recentCutoff) {
            $user = $userItems->first()->user;
            $lastContacted = $lastContacts->get($userItems->first()->user_id);
            $lastContactedAt = $lastContacted ? \Illuminate\Support\Carbon::parse($lastContacted) : null;

            return [
                'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'phone' => $user->phone] : null,
                'items' => $userItems->map(fn (CartItem $i) => $this->cartItemRow($i))->values(),
                'last_contacted_at'  => $lastContactedAt?->toIso8601String(),
                'contacted_recently' => $lastContactedAt ? $lastContactedAt->gte($recentCutoff) : false,
            ];
        })->values();

        return response()->json([
            'data' => [
                'clinic' => ['id' => $clinic->id, 'name' => $clinic->name, 'slug' => $clinic->slug],
                'carts'  => $carts,
            ],
        ]);
    }
}
