<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LandingPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Browse the leads/customers a single landing page produced — every booking
 * whose landing_page_id points at this page, with the customer + service +
 * attribution. This is the per-page CRM lens the admin asked for.
 */
class LandingPageCustomersController extends Controller
{
    public function index(Request $request, LandingPage $landingPage): JsonResponse
    {
        $this->authorize('view', $landingPage);

        $query = Booking::query()
            ->where('landing_page_id', $landingPage->id)
            ->with(['service:id,name', 'customer:id,name', 'user:id,name'])
            ->latest();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('reference_code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('filter.status')->toString()) {
            $query->where('status', $status);
        }

        $perPage = min(max((int) $request->input('per_page', 30), 1), 100);
        $page = $query->paginate($perPage)->withQueryString();

        // Headline counters for the tab header (independent of the page filter).
        $base = Booking::where('landing_page_id', $landingPage->id);
        $totals = [
            'total'      => (clone $base)->count(),
            'registered' => (clone $base)->whereNotNull('user_id')->count(),
            'completed'  => (clone $base)->where('status', Booking::STATUS_COMPLETED)->count(),
        ];

        return response()->json([
            'data' => collect($page->items())->map(fn (Booking $b) => [
                'id'             => $b->id,
                'reference_code' => $b->reference_code,
                'customer_name'  => $b->customer_name,
                'customer_phone' => $b->customer_phone,
                'service'        => $b->service?->name,
                'status'         => $b->status,
                'acquisition'    => $b->acquisition_source,
                'utm_source'     => $b->utm_source,
                'utm_campaign'   => $b->utm_campaign,
                'is_registered'  => $b->user_id !== null,
                'customer_id'    => $b->customer_id,
                'created_at'     => $b->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'from'         => $page->firstItem(),
                'to'           => $page->lastItem(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
            'totals' => $totals,
        ]);
    }
}
