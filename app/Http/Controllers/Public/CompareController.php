<?php

namespace App\Http\Controllers\Public;

use App\Enums\ImpressionSource;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Services\ImpressionTrackerService;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;

/**
 * Side-by-side comparison of up to 3 clinics. Selection lives client-side
 * (localStorage) and is passed here as ?ids=1,2,3.
 */
class CompareController extends Controller
{
    public function index(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($v) => (int) trim($v))
            ->filter()
            ->unique()
            ->take(3)
            ->values();

        $minPrice = fn ($q) => $q->where('is_active', true)->where('approval_status', 'approved')->whereNotNull('price');

        $clinics = collect();
        if ($ids->isNotEmpty()) {
            $clinics = Clinic::publiclyVisible()
                ->whereIn('id', $ids)
                ->with(['city', 'categories'])
                ->withAvg('googleReviews', 'rating')
                ->withCount(['googleReviews', 'bookings'])
                ->withCount(['services as active_services_count' => fn ($q) => $q->where('is_active', true)->where('approval_status', 'approved')])
                ->withMin(['services as min_price' => $minPrice], 'price')
                ->get()
                // Preserve the order the user selected them in.
                ->sortBy(fn (Clinic $c) => $ids->search($c->id))
                ->values();

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
}
