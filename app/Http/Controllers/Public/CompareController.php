<?php

namespace App\Http\Controllers\Public;

use App\Enums\ImpressionSource;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Offer;
use App\Models\Package;
use App\Models\Service;
use App\Services\ImpressionTrackerService;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;

/**
 * Side-by-side comparison of up to 3 items of the SAME type. Selection lives
 * client-side (one localStorage bucket per type) and is passed here as
 * ?type=service&ids=1,2,3. Mixing types is impossible by design — each type
 * has its own bucket, its own query shape, and its own comparison table,
 * because their attributes (a service's price vs. an offer's discount vs. a
 * package's included-services list) don't line up in one grid.
 */
class CompareController extends Controller
{
    public function index(Request $request)
    {
        $type = (string) $request->query('type', 'clinic');

        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($v) => (int) trim($v))
            ->filter()
            ->unique()
            ->take(3)
            ->values();

        return match ($type) {
            'service' => $this->services($ids),
            'offer'   => $this->offers($ids),
            'package' => $this->packages($ids),
            default   => $this->clinics($ids, $request),
        };
    }

    /** Re-order a freshly fetched collection to match the user's selection order. */
    private function inSelectedOrder($items, $ids)
    {
        return $items->sortBy(fn ($m) => $ids->search($m->id))->values();
    }

    private function clinics($ids, Request $request)
    {
        $minPrice = fn ($q) => $q->where('is_active', true)->where('approval_status', 'approved')->whereNotNull('price');

        $clinics = collect();
        if ($ids->isNotEmpty()) {
            $clinics = Clinic::publiclyVisible()
                ->whereIn('id', $ids)
                ->with(['city', 'categories'])
                ->withAvg('googleReviews', 'rating')
                ->withCount(['googleReviews', 'bookings'])
                ->withCount(['services as active_services_count' => fn ($q) => $q->where('is_active', true)->where('approval_status', 'approved')->notCatchall()])
                ->withMin(['services as min_price' => $minPrice], 'price')
                ->get();
            $clinics = $this->inSelectedOrder($clinics, $ids);

            // Bump impressions for everyone that surfaced in the
            // comparison view — clinic-only, no service-level cascade
            // because the compare layout shows clinic-wide summaries,
            // not specific service cards.
            app(ImpressionTrackerService::class)
                ->trackManyClinics($clinics->pluck('id')->all(), ImpressionSource::COMPARE);

            // Profile timeline event.
            if (auth('web')->check()) {
                app(UserActivityLogger::class)->logCompare(
                    $request, auth('web')->id(), $clinics->pluck('id')->all(),
                );
            }
        }

        return view('public.compare', compact('clinics'));
    }

    private function services($ids)
    {
        $services = collect();
        if ($ids->isNotEmpty()) {
            $services = Service::approvedPublic()
                ->whereIn('id', $ids)
                ->whereHas('clinic', fn ($q) => $q->publiclyVisible())
                ->with(['categories', 'clinic' => fn ($q) => $q->withAvg('googleReviews', 'rating')])
                ->get();
            $services = $this->inSelectedOrder($services, $ids);
        }

        return view('public.compare-service', compact('services'));
    }

    private function offers($ids)
    {
        $offers = collect();
        if ($ids->isNotEmpty()) {
            $offers = Offer::runningNow()
                ->whereIn('id', $ids)
                ->whereHas('clinic', fn ($q) => $q->publiclyVisible())
                ->with(['service', 'clinic' => fn ($q) => $q->withAvg('googleReviews', 'rating')])
                ->get();
            $offers = $this->inSelectedOrder($offers, $ids);
        }

        return view('public.compare-offer', compact('offers'));
    }

    private function packages($ids)
    {
        $packages = collect();
        if ($ids->isNotEmpty()) {
            $packages = Package::query()
                ->whereIn('id', $ids)
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', today()))
                ->whereHas('clinic', fn ($q) => $q->publiclyVisible())
                ->with(['services', 'clinic'])
                ->get();
            $packages = $this->inSelectedOrder($packages, $ids);
        }

        return view('public.compare-package', compact('packages'));
    }
}
