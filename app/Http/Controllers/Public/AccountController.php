<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Complaint;
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
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . auth('web')->id(),
        ]);

        auth('web')->user()->update($validated);
        return back()->with('success', __('site.account_updated'));
    }

    public function bookings()
    {
        $user = auth('web')->user();
        $bookings = $user
            ->bookings()
            ->with(['clinic.city', 'service'])
            ->latest()
            ->paginate(10);

        $bookingsCount = $user->bookings()->count();
        $favoritesCount = $user->favorites()->count();

        return view('public.account.bookings', compact('bookings', 'user', 'bookingsCount', 'favoritesCount'));
    }

    public function favorites()
    {
        $favorites = auth('web')->user()
            ->favorites()
            ->with(['city', 'categories'])
            ->paginate(12);

        return view('public.account.favorites', compact('favorites'));
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

    public function toggleFavorite(Clinic $clinic)
    {
        $user = auth('web')->user();

        if ($user->hasFavorited($clinic)) {
            $user->favorites()->detach($clinic->id);
            return back()->with('success', __('site.favorite_removed'));
        }

        $user->favorites()->attach($clinic->id);
        return back()->with('success', __('site.favorite_added'));
    }
}
