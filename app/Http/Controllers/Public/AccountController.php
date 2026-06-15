<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Complaint;
use App\Models\Offer;
use App\Models\Relative;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show()
    {
        $user = auth('web')->user();
        $bookingsCount = $user->bookings()->count();
        $favoritesCount = $user->favorites()->count();

        return view('public.account.profile', compact('user', 'bookingsCount', 'favoritesCount'));
    }

    public function update(Request $request)
    {
        $user = auth('web')->user();

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
        ]);

        // Snapshot the before/after so the profile timeline can show
        // "name: oldX → newY". Only diffed fields are recorded.
        $changes = [];
        foreach ($validated as $field => $newVal) {
            $oldVal = $user->{$field};
            if ($oldVal != $newVal) {
                $changes[$field] = ['from' => $oldVal, 'to' => $newVal];
            }
        }

        $user->update($validated);

        if (! empty($changes)) {
            app(\App\Services\UserActivityLogger::class)->logAccountEdit(
                $request, $user->id, $changes,
            );
        }

        return back()->with('success', __('site.account_updated'));
    }

    public function bookings(Request $request)
    {
        $user = auth('web')->user();

        // "self" = bookings the user made for themselves (no relative_id)
        // "relatives" = bookings they placed on behalf of saved relatives
        // "all" (default) = both
        $filter = $request->query('filter', 'all');
        if (! in_array($filter, ['all', 'self', 'relatives'], true)) {
            $filter = 'all';
        }

        $bookings = $user
            ->bookings()
            ->with(['clinic.city', 'service', 'relative'])
            ->when($filter === 'self', fn ($q) => $q->whereNull('relative_id'))
            ->when($filter === 'relatives', fn ($q) => $q->whereNotNull('relative_id'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $bookingsCount = $user->bookings()->count();
        $favoritesCount = $user->favorites()->count();

        return view('public.account.bookings', compact(
            'bookings', 'user', 'bookingsCount', 'favoritesCount', 'filter',
        ));
    }

    public function favorites()
    {
        $user = auth('web')->user();

        $favorites = $user
            ->favorites()
            ->with(['city', 'categories'])
            ->paginate(12);

        // Saved services + offers (polymorphic). withTrashed so deleted
        // items stay visible, labelled "محذوف"; expired offers are flagged
        // from their date window in the view.
        $saved = $user->savedItems()->with(['favoritable.clinic'])->latest()->get();
        $savedServices = $saved->where('favoritable_type', Service::class)
            ->pluck('favoritable')->filter()->values();
        $savedOffers = $saved->where('favoritable_type', Offer::class)
            ->pluck('favoritable')->filter()->values();

        return view('public.account.favorites', compact('favorites', 'savedServices', 'savedOffers'));
    }

    /**
     * Full, searchable/filterable list of the complexes the customer follows.
     * The homepage strip caps mobile to 6 and links here for the rest.
     */
    public function following(Request $request)
    {
        $user = auth('web')->user();
        $ids  = $user->following()->pluck('clinics.id');

        $q       = trim((string) $request->query('q', ''));
        $cityId  = $request->query('city');
        $catSlug = $request->query('category');

        $minPrice = fn ($qq) => $qq->where('is_active', true)
            ->where('approval_status', 'approved')
            ->whereNotNull('price');

        $clinics = Clinic::publiclyVisible()
            ->whereIn('clinics.id', $ids)
            ->when($q !== '', fn ($qq) => $qq->where('clinics.name', 'like', "%{$q}%"))
            ->when($cityId, fn ($qq) => $qq->where('clinics.city_id', $cityId))
            ->when($catSlug, fn ($qq) => $qq->whereHas('categories', fn ($c) => $c->where('slug', $catSlug)))
            ->with(['city', 'categories'])
            ->withAvg('googleReviews', 'rating')
            ->withCount('bookings')
            ->withMin(['services as min_price' => $minPrice], 'price')
            ->rankedForListing()
            ->paginate(12)
            ->withQueryString();

        // Filter options drawn from the followed set only, so every choice
        // actually narrows the list (no dead options).
        $followed   = Clinic::whereIn('id', $ids)
            ->with(['city:id,name,name_en', 'categories:id,name,name_en,slug'])
            ->get();
        $cities     = $followed->pluck('city')->filter()->unique('id')->sortBy('name')->values();
        $categories = $followed->pluck('categories')->flatten()->unique('id')->sortBy('name')->values();

        return view('public.account.following', compact(
            'clinics', 'cities', 'categories', 'q', 'cityId', 'catSlug', 'user',
        ));
    }

    /**
     * Full, searchable/filterable list of live offers across the complexes
     * the customer follows. Companion to following() for the offers strip.
     */
    public function followingOffers(Request $request)
    {
        $user = auth('web')->user();
        $ids  = $user->following()->pluck('clinics.id');

        $q        = trim((string) $request->query('q', ''));
        $catSlug  = $request->query('category');
        $clinicId = $request->query('clinic');

        $offers = Offer::query()
            ->runningNow()
            ->whereIn('clinic_id', $ids)
            ->whereHas('clinic', fn ($c) => $c->publiclyVisible())
            ->when($q !== '', fn ($qq) => $qq->where('title', 'like', "%{$q}%"))
            ->when($clinicId, fn ($qq) => $qq->where('clinic_id', $clinicId))
            ->when($catSlug, fn ($qq) => $qq->whereHas('service.categories', fn ($c) => $c->where('slug', $catSlug)))
            ->with([
                'clinic:id,name,slug,city_id',
                'clinic.city:id,name',
                'service:id,name,image',
                'service.categories:id,name,name_en,slug,emoji',
            ])
            ->orderByDesc('is_featured')
            ->orderByDesc('starts_at')
            ->paginate(12)
            ->withQueryString();

        // Filter options: followed complexes + only the categories that
        // actually have a service in those complexes.
        $clinics    = Clinic::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
        $categories = \App\Models\Category::query()
            ->whereIn('id', function ($sub) use ($ids) {
                $sub->select('cs.category_id')
                    ->from('category_service as cs')
                    ->join('services as sv', 'sv.id', '=', 'cs.service_id')
                    ->whereIn('sv.clinic_id', $ids);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'name_en', 'slug']);

        return view('public.account.following-offers', compact(
            'offers', 'clinics', 'categories', 'q', 'catSlug', 'clinicId', 'user',
        ));
    }

    /**
     * Toggle a saved service/offer for the current customer. Resolves the
     * target withTrashed so un-saving a since-deleted item still works.
     */
    public function toggleSaved(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:service,offer',
            'id'   => 'required|integer',
        ]);

        $class = $data['type'] === 'service' ? Service::class : Offer::class;
        $model = $class::withTrashed()->find($data['id']);
        abort_unless($model, 404);

        $user = auth('web')->user();
        $existing = $user->savedItems()
            ->where('favoritable_type', $model->getMorphClass())
            ->where('favoritable_id', $model->getKey())
            ->first();

        if ($existing) {
            $existing->delete();
            $saved = false;
        } else {
            $user->savedItems()->create([
                'favoritable_type' => $model->getMorphClass(),
                'favoritable_id'   => $model->getKey(),
            ]);
            $saved = true;
        }

        // AJAX heart toggle (progressive enhancement): the card swaps state
        // in place without a full reload. Non-JS clients still get back().
        if ($request->expectsJson()) {
            return response()->json(['saved' => $saved]);
        }

        return back()->with('success', $saved ? __('site.saved_added') : __('site.saved_removed'));
    }

    public function quotes()
    {
        $user = auth('web')->user();
        $quotes = $user->priceQuoteRequests()
            ->with('cities:id,name,name_en')
            ->withCount('replies')
            ->latest()
            ->paginate(10);

        $bookingsCount = $user->bookings()->count();
        $favoritesCount = $user->favorites()->count();

        return view('public.account.quotes', compact('quotes', 'user', 'bookingsCount', 'favoritesCount'));
    }

    public function complaints()
    {
        $user = auth('web')->user();

        $complaints = Complaint::where('user_id', $user->id)
            ->where('source', 'customer')
            ->with('clinic:id,name')
            ->latest()
            ->paginate(10);

        // Clinics the customer has dealt with — offered as the optional target.
        $clinics = Clinic::whereIn('id', $user->bookings()->select('clinic_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('public.account.complaints', compact('complaints', 'clinics', 'user'));
    }

    public function storeComplaint(Request $request)
    {
        $user = auth('web')->user();

        $validated = $request->validate([
            'type'        => 'required|in:' . implode(',', Complaint::TYPES),
            'clinic_id'   => 'nullable|integer|exists:clinics,id',
            'subject'     => 'required|string|max:255',
            'description' => 'required|string|min:10|max:2000',
        ]);

        $complaint = Complaint::create([
            'source'         => 'customer',
            'user_id'        => $user->id,
            'clinic_id'      => $validated['clinic_id'] ?? null,
            'customer_name'  => $user->name ?: __('site.account_customer'),
            'customer_phone' => $user->phone ?? '',
            'customer_email' => $user->email,
            'type'           => $validated['type'],
            'priority'       => 'medium',
            'status'         => 'new',
            'subject'        => $validated['subject'],
            'description'    => $validated['description'],
        ]);

        $this->dispatchComplaintNotifications($complaint, $user);

        return back()->with('success', __('site.complaint_sent'));
    }

    /**
     * Notify the targeted complex (if any) and every active admin when a
     * customer files a complaint. Same platform_notifications mechanism the
     * category-requests and clinic-reports flows use, so the existing bell
     * UI surfaces these without extra wiring.
     */
    private function dispatchComplaintNotifications(Complaint $complaint, $user): void
    {
        $clinicName = $complaint->clinic?->name;
        $userName   = $user->name ?: __('site.account_customer');
        $body       = $clinicName
            ? "{$userName} عن {$clinicName}: {$complaint->subject}"
            : "{$userName}: {$complaint->subject}";

        // 1) Targeted complex — only when the customer picked one. Skipping
        //    this for "general" complaints keeps the clinic inbox focused on
        //    complaints that actually concern them.
        if ($complaint->clinic_id) {
            \App\Models\PlatformNotification::create([
                'notifiable_type' => \App\Models\Clinic::class,
                'notifiable_id'   => $complaint->clinic_id,
                'type'            => 'complaint.new',
                'icon'            => 'heroicon-o-exclamation-triangle',
                'url'             => '/app/clinic/complaints',
                'priority'        => 'high',
                'title'           => __('site.complaint_notification_clinic_title'),
                'body'            => $body,
                'data'            => ['complaint_id' => $complaint->id],
            ]);
        }

        // 2) Every active admin so the review queue isn't missed.
        foreach (\App\Models\Admin::where('is_active', true)->get(['id']) as $admin) {
            \App\Models\PlatformNotification::create([
                'notifiable_type' => \App\Models\Admin::class,
                'notifiable_id'   => $admin->id,
                'type'            => 'complaint.new',
                'icon'            => 'heroicon-o-exclamation-triangle',
                'url'             => '/app/admin/complaints',
                'priority'        => 'normal',
                'title'           => __('site.complaint_notification_admin_title'),
                'body'            => $body,
                'data'            => ['complaint_id' => $complaint->id, 'clinic_id' => $complaint->clinic_id],
            ]);
        }
    }

    /**
     * Platform-level reports the customer raises directly with admins
     * (bug, suggestion, abuse, inappropriate content). Sits next to
     * /account/complaints — complaints handle clinic-specific issues,
     * reports are everything else.
     */
    public function reports()
    {
        $user = auth('web')->user();

        $reports = \App\Models\CustomerReport::where('user_id', $user->id)
            ->with('clinic:id,name')
            ->latest()
            ->paginate(10);

        $clinics = Clinic::whereIn('id', $user->bookings()->select('clinic_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('public.account.reports', compact('reports', 'clinics', 'user'));
    }

    public function storeReport(Request $request)
    {
        $user = auth('web')->user();

        $validated = $request->validate([
            'type'        => 'required|in:' . implode(',', \App\Models\CustomerReport::TYPES),
            'clinic_id'   => 'nullable|integer|exists:clinics,id',
            'priority'    => 'nullable|in:low,medium,high',
            'subject'     => 'required|string|max:255',
            'description' => 'required|string|min:10|max:2000',
        ]);

        $report = \App\Models\CustomerReport::create([
            'reference_code' => 'CRP-' . strtoupper(\Illuminate\Support\Str::random(8)),
            'user_id'        => $user->id,
            'clinic_id'      => $validated['clinic_id'] ?? null,
            'type'           => $validated['type'],
            'priority'       => $validated['priority'] ?? 'medium',
            'status'         => 'new',
            'subject'        => $validated['subject'],
            'description'    => $validated['description'],
        ]);

        // Reports go ONLY to platform admins — that's the whole point of
        // distinguishing them from complaints (which fan out to the clinic).
        foreach (\App\Models\Admin::where('is_active', true)->get(['id']) as $admin) {
            \App\Models\PlatformNotification::create([
                'notifiable_type' => \App\Models\Admin::class,
                'notifiable_id'   => $admin->id,
                'type'            => 'customer_report.new',
                'icon'            => 'heroicon-o-flag',
                'url'             => '/app/admin/customer-reports',
                'priority'        => $report->priority === 'high' ? 'urgent' : 'normal',
                'title'           => __('site.customer_report_notification_title'),
                'body'            => ($user->name ?: __('site.account_customer')) . ': ' . $report->subject,
                'data'            => ['customer_report_id' => $report->id, 'type' => $report->type, 'clinic_id' => $report->clinic_id],
            ]);
        }

        return back()->with('success', __('site.report_sent'));
    }

    public function toggleFavorite(Request $request, Clinic $clinic)
    {
        $user = auth('web')->user();

        if ($user->hasFavorited($clinic)) {
            $user->favorites()->detach($clinic->id);
            $favorited = false;
        } else {
            $user->favorites()->attach($clinic->id);
            $favorited = true;
        }

        // AJAX heart toggle (progressive enhancement): swap in place without a
        // full reload. Non-JS clients still fall back to back().
        if ($request->expectsJson()) {
            return response()->json(['saved' => $favorited]);
        }

        return back()->with('success', $favorited ? __('site.favorite_added') : __('site.favorite_removed'));
    }

    /**
     * Follow / unfollow a complex. Reachable by guests: a guest is sent
     * through the OTP login with the intended follow stashed in the
     * session, then OtpController applies it and returns them here.
     */
    public function toggleFollow(Clinic $clinic)
    {
        $user = auth('web')->user();

        if (! $user) {
            // Remember the intent + where to come back to, then push the
            // guest into the OTP login. Mirrors pending_booking / pending_quote.
            session()->put('pending_follow', $clinic->id);
            session()->put('url.intended', route('clinic.show', $clinic->slug));

            return redirect()->route('login');
        }

        if ($user->isFollowing($clinic)) {
            $user->following()->detach($clinic->id);
            return back()->with('success', __('site.follow_removed'));
        }

        $user->following()->syncWithoutDetaching([$clinic->id]);
        return back()->with('success', __('site.follow_added'));
    }

    /**
     * AJAX edit a saved relative from the booking-form card. Returns the
     * updated row so the front-end can swap the card text in place
     * without losing the booker's mid-flow form state.
     */
    public function updateRelative(Request $request, Relative $relative): JsonResponse
    {
        $this->authorizeRelative($relative);

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'relationship_type'   => 'required|string|in:' . implode(',', Relative::TYPES),
            'relationship_label'  => 'nullable|string|max:50',
            'phone'               => 'required|string|max:20|regex:/^05\d{8}$/',
        ], [
            'phone.regex' => __('site.phone_invalid'),
        ]);

        $relative->update($validated);

        return response()->json([
            'data' => $this->relativePayload($relative->fresh()),
        ]);
    }

    /**
     * AJAX delete (soft) a saved relative. Past bookings keep their
     * snapshotted customer_name/customer_phone so the history stays
     * readable — only the picker entry disappears.
     */
    public function destroyRelative(Relative $relative): JsonResponse
    {
        $this->authorizeRelative($relative);

        $relative->delete();

        return response()->json([
            'data' => [
                'id'         => $relative->id,
                'deleted'    => true,
                'remaining'  => auth('web')->user()->relatives()->count(),
                'max'        => Relative::MAX_PER_USER,
            ],
        ]);
    }

    /** 404 if the relative doesn't belong to the auth user — prevents id-guess. */
    private function authorizeRelative(Relative $relative): void
    {
        abort_unless($relative->user_id === auth('web')->id(), 404);
    }

    private function relativePayload(Relative $rel): array
    {
        return [
            'id'                  => $rel->id,
            'name'                => $rel->name,
            'relationship_type'   => $rel->relationship_type,
            'relationship_label'  => $rel->relationship_label,
            'relationship_display' => $rel->relationship_type === 'other'
                ? ($rel->relationship_label ?: __('site.relative_type_other'))
                : __('site.relative_type.' . $rel->relationship_type),
            'phone'               => $rel->phone,
        ];
    }
}
