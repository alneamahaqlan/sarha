<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\SavedItem;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super-admin-only view over customer favourites (`saved_items` — saved
 * services + offers). Surfaces the raw list plus a "most saved" roll-up
 * that doubles as a market demand signal. Admin-only by design: the data
 * spans every clinic. `sales` admin role excluded.
 */
class SavedItemController extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_if(auth('admin')->user()?->role === 'sales', 403, 'غير مصرّح.');
    }

    /** FQCN morph type → short token. */
    private function type(string $class): string
    {
        return match ($class) {
            Service::class => 'service',
            Offer::class   => 'offer',
            default        => 'item',
        };
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $saved = SavedItem::query()
            ->with(['favoritable', 'user:id,name,phone'])
            ->latest()
            ->paginate(30);

        $rows = $saved->getCollection()->map(function (SavedItem $s) {
            $target = $s->favoritable;

            return [
                'id'        => $s->id,
                'type'      => $this->type($s->favoritable_type),
                'name'      => $target->name ?? $target->title ?? '—',
                'deleted'   => ! $target || (method_exists($target, 'trashed') && $target->trashed()),
                'user'      => $s->user ? ['id' => $s->user->id, 'name' => $s->user->name, 'phone' => $s->user->phone] : null,
                'saved_at'  => $s->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data'     => $rows,
            'meta'     => [
                'current_page' => $saved->currentPage(),
                'last_page'    => $saved->lastPage(),
                'total'        => $saved->total(),
            ],
            'top_saved' => $this->topSaved(),
        ]);
    }

    /** Most-saved services/offers across the platform (demand signal). */
    private function topSaved(): array
    {
        $grouped = SavedItem::query()
            ->selectRaw('favoritable_type, favoritable_id, COUNT(*) as saves')
            ->groupBy('favoritable_type', 'favoritable_id')
            ->orderByDesc('saves')
            ->limit(10)
            ->get();

        // Resolve names per morph type in one query each (avoids N+1).
        $byType = $grouped->groupBy('favoritable_type');
        $names = [];
        foreach ($byType as $class => $rows) {
            if (! class_exists($class)) {
                continue;
            }
            $names[$class] = $class::withTrashed()
                ->whereIn('id', $rows->pluck('favoritable_id'))
                ->get()
                ->keyBy('id');
        }

        return $grouped->map(function ($row) use ($names) {
            $model = $names[$row->favoritable_type][$row->favoritable_id] ?? null;

            return [
                'type'  => $this->type($row->favoritable_type),
                'name'  => $model->name ?? $model->title ?? '—',
                'saves' => (int) $row->saves,
            ];
        })->values()->all();
    }
}
