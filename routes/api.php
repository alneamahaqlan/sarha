<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Admin\AiCenterAnalyticsController as AdminAiCenterAnalyticsController;
use App\Http\Controllers\Api\V1\Admin\AiCenterConversationsController as AdminAiCenterConversationsController;
use App\Http\Controllers\Api\V1\Admin\AiCenterDashboardWidgetController as AdminAiCenterDashboardWidgetController;
use App\Http\Controllers\Api\V1\Admin\AiRestrictionController as AdminAiRestrictionController;
use App\Http\Controllers\Api\V1\Admin\AiResponseTemplateController as AdminAiResponseTemplateController;
use App\Http\Controllers\Api\V1\Admin\UserAiInterestsController as AdminUserAiInterestsController;
use App\Http\Controllers\Api\V1\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\BookingController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\CampaignRequestController as AdminCampaignRequestController;
use App\Http\Controllers\Api\V1\Admin\CategoryRequestController as AdminCategoryRequestController;
use App\Http\Controllers\Api\V1\Admin\CityController;
use App\Http\Controllers\Api\V1\Admin\ClinicController;
use App\Http\Controllers\Api\V1\Admin\HomepageBannerSlideController;
use App\Http\Controllers\Api\V1\Admin\HomepageSectionController;
use App\Http\Controllers\Api\V1\Admin\ComplaintController;
use App\Http\Controllers\Api\V1\Admin\PriceQuoteRequestController;
use App\Http\Controllers\Api\V1\Admin\SalesLeadController;
use App\Http\Controllers\Api\V1\Admin\ServiceController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionController;
use App\Http\Controllers\Api\V1\Admin\SystemSettingController;
use App\Http\Controllers\Api\V1\Admin\MassNotifyController;
use App\Http\Controllers\Api\V1\Admin\NavigationLinkController;
use App\Http\Controllers\Api\V1\Admin\StaticPageController;
use App\Http\Controllers\Api\V1\Admin\LandingPageController;
use App\Http\Controllers\Api\V1\Admin\LandingPageBlockController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\UserProfileController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\SeederCenterController;
use App\Http\Controllers\Api\V1\Clinic\ArticleController as ClinicArticleController;
use App\Http\Controllers\Api\V1\Clinic\BeforeAfterController as ClinicBeforeAfterController;
use App\Http\Controllers\Api\V1\Clinic\StoryController as ClinicStoryController;
use App\Http\Controllers\Api\V1\Clinic\BookingController as ClinicBookingController;
use App\Http\Controllers\Api\V1\Clinic\CategoryRequestController as ClinicCategoryRequestController;
use App\Http\Controllers\Api\V1\Clinic\ComplaintController as ClinicComplaintController;
use App\Http\Controllers\Api\V1\Clinic\ReportController as ClinicReportController;
use App\Http\Controllers\Api\V1\Admin\ClinicReportController as AdminClinicReportController;
use App\Http\Controllers\Api\V1\Admin\CustomerReportController as AdminCustomerReportController;
use App\Http\Controllers\Api\V1\Clinic\DoctorController as ClinicDoctorController;
use App\Http\Controllers\Api\V1\Clinic\OutreachController as ClinicOutreachController;
use App\Http\Controllers\Api\V1\Clinic\OfferController as ClinicOfferController;
use App\Http\Controllers\Api\V1\Clinic\PackageController as ClinicPackageController;
use App\Http\Controllers\Api\V1\Clinic\PageSectionController as ClinicPageSectionController;
use App\Http\Controllers\Api\V1\Clinic\SubClinicController as ClinicSubClinicController;
use App\Http\Controllers\Api\V1\Clinic\DashboardController as ClinicDashboardController;
use App\Http\Controllers\Api\V1\Clinic\ImportServicesController as ClinicImportServicesController;
use App\Http\Controllers\Api\V1\Clinic\PriceQuoteRequestController as ClinicPriceQuoteRequestController;
use App\Http\Controllers\Api\V1\Clinic\ProfileController as ClinicProfileController;
use App\Http\Controllers\Api\V1\Clinic\ServiceController as ClinicServiceController;
use App\Http\Controllers\Api\V1\Clinic\StatsController as ClinicStatsController;
use App\Http\Controllers\Api\V1\Clinic\WorkingHoursController as ClinicWorkingHoursController;
use App\Http\Controllers\Api\V1\Shared\AiChatController;
use App\Http\Controllers\Api\V1\Shared\AuthController;
use App\Http\Controllers\Api\V1\Shared\ImpersonationController as ApiImpersonationController;
use App\Http\Controllers\Api\V1\Shared\LookupController;
use App\Http\Controllers\Api\V1\Shared\NotificationController;
use App\Http\Controllers\Api\V1\Shared\UploadController;
use App\Models\Booking;
use App\Models\Clinic;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| All routes here are stateful (Sanctum SPA) — see Middleware::statefulApi
| in bootstrap/app.php. Auth is enforced per-guard via the api.guard
| middleware alias (App\Http\Middleware\EnsureApiGuard).
|
| Backwards-compat with Filament panels: these routes are additive only.
| They never modify or replace anything Filament uses; they call the SAME
| underlying models / services that Filament does.
|
*/

Route::prefix('v1')->middleware(['api.locale'])->group(function () {

    // -------------------- Public --------------------
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1');

    // -------------------- Authenticated (any guard) --------------------
    Route::middleware('auth:admin,clinic,web')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Web Push (Phase C) — VAPID public key is whitelist-safe to
        // expose to any auth user; the subscribe/unsubscribe endpoints
        // bind the row to whichever guard's session is active.
        Route::get('push/vapid-public-key', [\App\Http\Controllers\Api\V1\PushSubscriptionController::class, 'vapidPublicKey']);
        Route::post('push/subscribe',   [\App\Http\Controllers\Api\V1\PushSubscriptionController::class, 'subscribe']);
        Route::delete('push/unsubscribe', [\App\Http\Controllers\Api\V1\PushSubscriptionController::class, 'unsubscribe']);

        Route::get('lookups/clinics', [LookupController::class, 'clinics']);
        Route::get('lookups/cities', [LookupController::class, 'cities']);
        Route::get('lookups/categories', [LookupController::class, 'categories']);
        Route::get('lookups/admins', [LookupController::class, 'admins']);
        Route::get('lookups/sub-clinics', [LookupController::class, 'subClinics']);
        Route::get('lookups/services', [LookupController::class, 'services']);
        Route::get('lookups/offers', [LookupController::class, 'offers']);
        Route::get('lookups/doctors', [LookupController::class, 'doctors']);
        Route::get('lookups/before-after', [LookupController::class, 'beforeAfter']);

        // Notification bell — same PlatformNotification model the Filament Livewire bell reads.
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        // Stop impersonation (start lives on /api/v1/admin/clinics/{clinic}/impersonate).
        Route::post('impersonation/stop', [ApiImpersonationController::class, 'stop'])->name('impersonation.stop');

        // AI assistant — same AiAssistantService::ask the Filament Livewire chat calls.
        Route::post('ai-chat', [AiChatController::class, 'ask'])->middleware('throttle:30,1')->name('ai-chat.ask');

        // Image uploads (logos, gallery, article covers). Allowed dirs are whitelisted
        // in UploadFileRequest to match Filament's FileUpload::directory() targets.
        Route::post('uploads', [UploadController::class, 'store'])->name('uploads.store');
    });

    // -------------------- Admin guard --------------------
    Route::prefix('admin')->middleware('api.guard:admin')->group(function () {
        // Dashboard widgets (StatsOverview + LatestBookings + bookings trend).
        Route::get('dashboard/stats', [AdminDashboardController::class, 'stats'])->name('admin.dashboard.stats');
        Route::get('dashboard/latest-bookings', [AdminDashboardController::class, 'latestBookings'])->name('admin.dashboard.latest-bookings');
        Route::get('dashboard/bookings-trend', [AdminDashboardController::class, 'bookingsTrend'])->name('admin.dashboard.bookings-trend');
        Route::get('dashboard/nav-badges', [AdminDashboardController::class, 'navBadges'])->name('admin.dashboard.nav-badges');
        Route::get('dashboard/sections', [AdminDashboardController::class, 'sections'])->name('admin.dashboard.sections');
        Route::get('dashboard/analytics', [AdminDashboardController::class, 'analytics'])->name('admin.dashboard.analytics');

        // ── Seeder Center (مركز السيدر) — super-admin demo-data management.
        // Inventory / soft-hide / restore / purge / reseed of seeded batches
        // without touching real, app-entered data. Gated to super_admin in
        // the controller (every method calls guard()).
        Route::get('seeder-center', [SeederCenterController::class, 'inventory'])->name('admin.seeder-center.inventory');
        Route::get('seeder-center/{batch}/breakdown', [SeederCenterController::class, 'breakdown'])->name('admin.seeder-center.breakdown');
        Route::get('seeder-center/{batch}/conflicts', [SeederCenterController::class, 'conflicts'])->name('admin.seeder-center.conflicts');
        Route::post('seeder-center/{batch}/hide', [SeederCenterController::class, 'hide'])->name('admin.seeder-center.hide');
        Route::post('seeder-center/{batch}/unhide', [SeederCenterController::class, 'unhide'])->name('admin.seeder-center.unhide');
        Route::post('seeder-center/{batch}/purge', [SeederCenterController::class, 'purge'])->name('admin.seeder-center.purge');
        Route::post('seeder-center/{batch}/reseed', [SeederCenterController::class, 'reseed'])->name('admin.seeder-center.reseed');
        Route::get('seeder-center/runs/{run}', [SeederCenterController::class, 'runStatus'])->name('admin.seeder-center.run-status');

        Route::apiResource('cities', CityController::class);

        Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
        Route::apiResource('categories', CategoryController::class);

        // Static content pages (About/Privacy/Terms/Contact/FAQ) + the
        // header/footer navigation-link manager. Both follow the category
        // CRUD + reorder pattern.
        Route::post('static-pages/reorder', [StaticPageController::class, 'reorder'])->name('static-pages.reorder');
        Route::apiResource('static-pages', StaticPageController::class)->parameters(['static-pages' => 'static_page']);
        Route::post('navigation-links/reorder', [NavigationLinkController::class, 'reorder'])->name('navigation-links.reorder');
        Route::apiResource('navigation-links', NavigationLinkController::class)->parameters(['navigation-links' => 'navigation_link']);

        // ── Landing Pages (صفحات الهبوط) — super-admin builder. CRUD for the
        // page + nested CRUD/reorder for its drag-and-drop content blocks.
        // Block routes are declared before the apiResource so /blocks and
        // /blocks/reorder are not swallowed by the {landing_page} binding.
        Route::get('landing-pages/{landing_page}/blocks', [LandingPageBlockController::class, 'index'])->name('landing-pages.blocks.index');
        Route::post('landing-pages/{landing_page}/blocks/reorder', [LandingPageBlockController::class, 'reorder'])->name('landing-pages.blocks.reorder');
        Route::post('landing-pages/{landing_page}/blocks', [LandingPageBlockController::class, 'store'])->name('landing-pages.blocks.store');
        Route::patch('landing-pages/{landing_page}/blocks/{block}', [LandingPageBlockController::class, 'update'])->name('landing-pages.blocks.update');
        Route::delete('landing-pages/{landing_page}/blocks/{block}', [LandingPageBlockController::class, 'destroy'])->name('landing-pages.blocks.destroy');
        Route::get('landing-pages/{landing_page}/stats', [\App\Http\Controllers\Api\V1\Admin\LandingPageStatsController::class, 'show'])->name('landing-pages.stats');
        Route::get('landing-pages/{landing_page}/customers', [\App\Http\Controllers\Api\V1\Admin\LandingPageCustomersController::class, 'index'])->name('landing-pages.customers');
        Route::post('landing-pages/generate', [\App\Http\Controllers\Api\V1\Admin\LandingPageAiController::class, 'generate'])->middleware('throttle:20,1')->name('landing-pages.generate');
        Route::apiResource('landing-pages', LandingPageController::class)->parameters(['landing-pages' => 'landing_page']);

        // Subscription packages catalogue — full CRUD for the super-admin.
        // Deletion is rejected at controller level if any clinic is on the package.
        Route::apiResource('subscription-packages', \App\Http\Controllers\Api\V1\Admin\SubscriptionPackageController::class);

        // Badges Center — display badges (manual + automatic rules). Specific
        // routes precede the resource so they aren't captured by badges/{badge}.
        Route::get('badges/rules', [\App\Http\Controllers\Api\V1\Admin\BadgeController::class, 'rules'])->name('badges.rules');
        Route::get('badges/targets/search', [\App\Http\Controllers\Api\V1\Admin\BadgeController::class, 'searchTargets'])->name('badges.targets.search');
        Route::post('badges/recompute', [\App\Http\Controllers\Api\V1\Admin\BadgeController::class, 'recompute'])->name('badges.recompute');
        Route::post('badges/{badge}/targets', [\App\Http\Controllers\Api\V1\Admin\BadgeController::class, 'syncTargets'])->name('badges.targets');
        Route::apiResource('badges', \App\Http\Controllers\Api\V1\Admin\BadgeController::class);

        // UserResource has no Delete in Filament — restrict to index/show/store/update only.
        Route::apiResource('users', UserController::class)->except(['destroy']);

        // Super-admin "comprehensive user profile" — header, timeline,
        // tab loaders, hours heatmap, and the suspend / force-logout
        // quick actions. Every show() call writes a row to audit_logs.
        Route::get('users/{user}/profile', [UserProfileController::class, 'show'])->name('users.profile');
        Route::get('users/{user}/profile/timeline', [UserProfileController::class, 'timeline'])->name('users.profile.timeline');
        Route::get('users/{user}/profile/hours', [UserProfileController::class, 'hours'])->name('users.profile.hours');
        Route::get('users/{user}/profile/tab/{tab}', [UserProfileController::class, 'tab'])
            ->whereIn('tab', ['bookings', 'complaints', 'ai', 'sessions', 'favorites', 'quotes', 'account_edits'])
            ->name('users.profile.tab');
        Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/force-logout', [UserController::class, 'forceLogout'])->name('users.force-logout');

        // Admin (panel administrators) — route param renamed to avoid collision with the
        // 'admin' segment that the api.guard middleware uses.
        Route::apiResource('admins', AdminController::class)->parameter('admins', 'admin_user');

        Route::apiResource('services', ServiceController::class);

        // Articles — admin cross-clinic management. Observer enforces basic-plan publish limit.
        Route::apiResource('articles', AdminArticleController::class);

        // Booking — restore + forceDestroy need to resolve trashed rows, so we bind
        // the {booking_trashed} param with withTrashed() instead of default scope.
        Route::bind('booking_trashed', fn ($id) => Booking::withTrashed()->findOrFail($id));
        Route::get('bookings/status-counts', [BookingController::class, 'statusCounts'])->name('bookings.status-counts');
        Route::get('bookings/export', [BookingController::class, 'export'])->name('bookings.export');
        Route::post('bookings/{booking_trashed}/restore', [BookingController::class, 'restore'])->name('bookings.restore');
        Route::delete('bookings/{booking_trashed}/force', [BookingController::class, 'forceDestroy'])->name('bookings.force-destroy');
        Route::apiResource('bookings', BookingController::class);

        // Unified customer identity (primary name + per-clinic alternatives).
        Route::get('platform-customers/by-phone/{phone}', [\App\Http\Controllers\Api\V1\Admin\PlatformCustomerController::class, 'showByPhone'])
            ->where('phone', '.*')
            ->name('platform-customers.by-phone');

        // Complaint state-transition actions — each delegates to ComplaintService.
        Route::post('complaints/{complaint}/mark-in-review', [ComplaintController::class, 'markInReview'])->name('complaints.mark-in-review');
        Route::post('complaints/{complaint}/resolve', [ComplaintController::class, 'resolve'])->name('complaints.resolve');
        Route::post('complaints/{complaint}/reject', [ComplaintController::class, 'reject'])->name('complaints.reject');
        Route::post('complaints/{complaint}/notify-clinic', [ComplaintController::class, 'notifyClinic'])->name('complaints.notify-clinic');
        Route::post('complaints/{complaint}/reply', [ComplaintController::class, 'replyToCustomer'])->name('complaints.reply');
        Route::post('complaints/{complaint}/reopen', [ComplaintController::class, 'reopen'])->name('complaints.reopen');
        Route::apiResource('complaints', ComplaintController::class);

        // Verified-review moderation — the reported queue + hide(spam/abuse)/dismiss.
        Route::get('review-moderation', [\App\Http\Controllers\Api\V1\Admin\AdminVerifiedReviewController::class, 'index'])->name('admin.review-moderation.index');
        Route::post('review-moderation/{review}/moderate', [\App\Http\Controllers\Api\V1\Admin\AdminVerifiedReviewController::class, 'moderate'])->name('admin.review-moderation.moderate');

        // SalesLead — convert delegates to SalesLeadService (DB::transaction).
        Route::post('sales-leads/{salesLead}/convert', [SalesLeadController::class, 'convert'])->name('sales-leads.convert');
        // Lead timeline — list + log a manual activity (call/whatsapp/note/...).
        Route::get('sales-leads/{salesLead}/activities', [SalesLeadController::class, 'activities'])->name('sales-leads.activities.index');
        Route::post('sales-leads/{salesLead}/activities', [SalesLeadController::class, 'storeActivity'])->name('sales-leads.activities.store');
        Route::apiResource('sales-leads', SalesLeadController::class)->parameters(['sales-leads' => 'salesLead']);

        // Clinic-side platform reports — review queue. Kept separate from
        // /admin/complaints (which is customer→clinic only) so the two
        // workflows don't get tangled in the same view.
        Route::apiResource('clinic-reports', AdminClinicReportController::class)
            ->only(['index', 'show', 'update'])
            ->parameters(['clinic-reports' => 'clinicReport']);

        // Customer-side platform reports (bugs, suggestions, abuse) — also
        // their own queue. Same triage workflow as clinic-reports.
        Route::apiResource('customer-reports', AdminCustomerReportController::class)
            ->only(['index', 'show', 'update'])
            ->parameters(['customer-reports' => 'customerReport']);

        // Specialty (category) requests submitted by complexes — review queue.
        Route::get('category-requests', [AdminCategoryRequestController::class, 'index'])->name('category-requests.index');
        Route::post('category-requests/{categoryRequest}/approve', [AdminCategoryRequestController::class, 'approve'])->name('category-requests.approve');
        Route::post('category-requests/{categoryRequest}/reject', [AdminCategoryRequestController::class, 'reject'])->name('category-requests.reject');

        // Managed-campaign requests — complexes ask the platform to run a
        // prepared campaign (image+text+audience) externally. Intake queue +
        // recipient CSV export + close. No sending happens in-system.
        Route::get('campaign-requests', [AdminCampaignRequestController::class, 'index'])->name('campaign-requests.index');
        Route::get('campaign-requests/{campaign}', [AdminCampaignRequestController::class, 'show'])->name('campaign-requests.show');
        Route::get('campaign-requests/{campaign}/export', [AdminCampaignRequestController::class, 'export'])->name('campaign-requests.export');
        Route::post('campaign-requests/{campaign}/close', [AdminCampaignRequestController::class, 'close'])->name('campaign-requests.close');

        // Unified service catalog — review queue for clinic-proposed canonical
        // services. Approve flips the entry active + un-hides linked services.
        Route::get('catalog-services', [\App\Http\Controllers\Api\V1\Admin\CatalogServiceController::class, 'index'])->name('catalog-services.index');
        Route::patch('catalog-services/{catalogService}', [\App\Http\Controllers\Api\V1\Admin\CatalogServiceController::class, 'update'])->name('catalog-services.update');
        Route::post('catalog-services/{catalogService}/approve', [\App\Http\Controllers\Api\V1\Admin\CatalogServiceController::class, 'approve'])->name('catalog-services.approve');
        Route::post('catalog-services/{catalogService}/reject', [\App\Http\Controllers\Api\V1\Admin\CatalogServiceController::class, 'reject'])->name('catalog-services.reject');

        // Clinic — 6 action endpoints (approve/reject/activate/suspend/extend/impersonate)
        // each delegates to ClinicService. Soft-deleted rows reachable via {clinic_trashed}.
        Route::bind('clinic_trashed', fn ($id) => Clinic::withTrashed()->findOrFail($id));
        Route::post('clinics/bulk', [ClinicController::class, 'bulk'])->name('clinics.bulk');
        Route::post('clinics/import-sheet', [ClinicController::class, 'importSheet'])->name('clinics.import-sheet');
        Route::post('clinics/{clinic_trashed}/restore', [ClinicController::class, 'restore'])->name('clinics.restore');
        Route::post('clinics/{clinic}/approve', [ClinicController::class, 'approve'])->name('clinics.approve');
        Route::post('clinics/{clinic}/reject', [ClinicController::class, 'reject'])->name('clinics.reject');
        Route::post('clinics/{clinic}/activate', [ClinicController::class, 'activate'])->name('clinics.activate');
        Route::post('clinics/{clinic}/suspend', [ClinicController::class, 'suspend'])->name('clinics.suspend');
        Route::post('clinics/{clinic}/extend', [ClinicController::class, 'extend'])->name('clinics.extend');
        Route::post('clinics/{clinic}/impersonate', [ClinicController::class, 'impersonate'])->name('clinics.impersonate');
        Route::get('clinics/{clinic}/stats', [AdminDashboardController::class, 'clinicStats'])->name('clinics.stats');
        Route::get('clinics/{clinic}/structure', [ClinicController::class, 'structure'])->name('clinics.structure');
        // Login-password reveal + regenerate (super-admin only, audit-logged).
        Route::get('clinics/{clinic}/password', [ClinicController::class, 'password'])->name('clinics.password');
        Route::post('clinics/{clinic}/regenerate-password', [ClinicController::class, 'regeneratePassword'])->name('clinics.regenerate-password');
        Route::apiResource('clinics', ClinicController::class);

        // Subscription — Filament has no Delete action; restrict to index/show/store/update.
        // Lifecycle actions (one-click renew + cancel) wired before the resource so
        // {subscription} binding picks them up first.
        Route::post('subscriptions/{subscription}/renew',  [SubscriptionController::class, 'renew'])->name('subscriptions.renew');
        Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::apiResource('subscriptions', SubscriptionController::class)->except(['destroy']);

        // Price quote requests — clinic route param renamed to avoid conflict with /price-quotes.
        Route::apiResource('price-quotes', PriceQuoteRequestController::class)
            ->parameters(['price-quotes' => 'priceQuote']);

        // Audit logs — read-only (Filament: canCreate/canEdit/canDelete = false).
        Route::apiResource('audit-logs', AuditLogController::class)
            ->only(['index', 'show'])
            ->parameters(['audit-logs' => 'auditLog']);

        // System settings — Filament allows edit only (no create/delete). Cache::forget
        // is fired in the controller, mirroring EditAction::after().
        Route::apiResource('system-settings', SystemSettingController::class)
            ->only(['index', 'show', 'update'])
            ->parameters(['system-settings' => 'systemSetting']);

        // Marketing tracking pixels — global feature toggle + the clinic
        // activation request queue (approve/reject) + per-clinic kill-switch.
        Route::get('tracking/settings',  [\App\Http\Controllers\Api\V1\Admin\TrackingController::class, 'settings'])->name('admin.tracking.settings');
        Route::put('tracking/settings',  [\App\Http\Controllers\Api\V1\Admin\TrackingController::class, 'updateSettings'])->name('admin.tracking.settings.update');
        Route::get('tracking/requests',  [\App\Http\Controllers\Api\V1\Admin\TrackingController::class, 'requests'])->name('admin.tracking.requests');
        Route::post('clinics/{clinic}/tracking/approve', [\App\Http\Controllers\Api\V1\Admin\TrackingController::class, 'approve'])->name('admin.tracking.approve');
        Route::post('clinics/{clinic}/tracking/reject',  [\App\Http\Controllers\Api\V1\Admin\TrackingController::class, 'reject'])->name('admin.tracking.reject');
        Route::post('clinics/{clinic}/tracking/disable', [\App\Http\Controllers\Api\V1\Admin\TrackingController::class, 'disable'])->name('admin.tracking.disable');

        // Per-clinic cart feature — activation request queue + enable/disable.
        Route::get('cart/requests', [\App\Http\Controllers\Api\V1\Admin\CartController::class, 'requests'])->name('admin.cart.requests');
        Route::post('clinics/{clinic}/cart/approve', [\App\Http\Controllers\Api\V1\Admin\CartController::class, 'approve'])->name('admin.cart.approve');
        Route::post('clinics/{clinic}/cart/reject',  [\App\Http\Controllers\Api\V1\Admin\CartController::class, 'reject'])->name('admin.cart.reject');
        Route::post('clinics/{clinic}/cart/disable', [\App\Http\Controllers\Api\V1\Admin\CartController::class, 'disable'])->name('admin.cart.disable');

        // Per-clinic price-quote reply gate — moderation queue + enable/disable.
        Route::get('price-quote-access/requests', [\App\Http\Controllers\Api\V1\Admin\PriceQuoteAccessController::class, 'requests'])->name('admin.price-quote-access.requests');
        Route::post('clinics/{clinic}/price-quote-access/approve', [\App\Http\Controllers\Api\V1\Admin\PriceQuoteAccessController::class, 'approve'])->name('admin.price-quote-access.approve');
        Route::post('clinics/{clinic}/price-quote-access/reject',  [\App\Http\Controllers\Api\V1\Admin\PriceQuoteAccessController::class, 'reject'])->name('admin.price-quote-access.reject');
        Route::post('clinics/{clinic}/price-quote-access/disable', [\App\Http\Controllers\Api\V1\Admin\PriceQuoteAccessController::class, 'disable'])->name('admin.price-quote-access.disable');

        // Unified Requests & Permissions Center — aggregates every per-clinic
        // gate (ClinicGateRegistry) + external request queues + package flags.
        Route::get('access-center/meta',     [\App\Http\Controllers\Api\V1\Admin\AccessCenterController::class, 'meta'])->name('admin.access-center.meta');
        Route::get('access-center/pending',  [\App\Http\Controllers\Api\V1\Admin\AccessCenterController::class, 'pending'])->name('admin.access-center.pending');
        Route::get('access-center/clinics',  [\App\Http\Controllers\Api\V1\Admin\AccessCenterController::class, 'clinics'])->name('admin.access-center.clinics');
        Route::get('access-center/packages', [\App\Http\Controllers\Api\V1\Admin\AccessCenterController::class, 'packages'])->name('admin.access-center.packages');
        Route::post('access-center/gates/{gate}/clinics/{clinic}/action', [\App\Http\Controllers\Api\V1\Admin\AccessCenterController::class, 'action'])->name('admin.access-center.action');
        Route::post('access-center/landing-pages/{landing_page}/action', [\App\Http\Controllers\Api\V1\Admin\AccessCenterController::class, 'landingAction'])->name('admin.access-center.landing-action');

        // Abandoned carts (unbooked items) — per-clinic roll-up + drill-down. Read-only.
        Route::get('abandoned-carts', [\App\Http\Controllers\Api\V1\Admin\AbandonedCartController::class, 'index'])->name('admin.abandoned-carts.index');
        Route::get('abandoned-carts/{clinic}', [\App\Http\Controllers\Api\V1\Admin\AbandonedCartController::class, 'show'])->name('admin.abandoned-carts.show');

        // Customer favourites (saved services/offers) — admin-only.
        Route::get('favorites', [\App\Http\Controllers\Api\V1\Admin\SavedItemController::class, 'index'])->name('admin.favorites.index');

        // WhatsApp sender numbers (Wappi profiles) used for OTP delivery.
        // Full CRUD (capped at 5 in the request) + an end-to-end test send.
        Route::post('whatsapp-senders/{whatsappSender}/test', [\App\Http\Controllers\Api\V1\Admin\WhatsAppSenderController::class, 'test'])
            ->name('whatsapp-senders.test');
        Route::apiResource('whatsapp-senders', \App\Http\Controllers\Api\V1\Admin\WhatsAppSenderController::class)
            ->parameters(['whatsapp-senders' => 'whatsappSender']);

        // AI Center — admin-managed safety net for the public assistant.
        // The "Settings" tab is built on top of /system-settings?filter[group]=ai
        // (so the existing edit dialog handles encrypted API keys for free).
        // These routes back the "Restrictions & Instructions" tab.
        Route::apiResource('ai-restrictions', AdminAiRestrictionController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['ai-restrictions' => 'aiRestriction']);
        Route::apiResource('ai-response-templates', AdminAiResponseTemplateController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['ai-response-templates' => 'aiResponseTemplate']);

        // AI Center Phase 2 surfaces — analytics tab, conversations
        // browser, dashboard widget, per-user interests timeline.
        Route::get('ai-center/analytics',          [AdminAiCenterAnalyticsController::class,       'show']);
        Route::get('ai-center/dashboard-widget',   [AdminAiCenterDashboardWidgetController::class, 'show']);
        Route::get('ai-center/conversations',      [AdminAiCenterConversationsController::class,   'index']);
        Route::get('ai-center/conversations/{conversation}', [AdminAiCenterConversationsController::class, 'show'])
            ->where('conversation', '[0-9a-f-]{36}');
        Route::get('users/{user}/ai-interests', [AdminUserAiInterestsController::class, 'show']);

        // Mass notify — delegates to MassNotifyService (same code the Filament page calls).
        Route::post('mass-notify', [MassNotifyController::class, 'send'])->name('mass-notify.send');

        // Homepage CMS — admin-curated sections rendered on the public landing.
        // Reorder/list lives at /homepage-sections; banner-type rows own a nested
        // slides resource scoped to their parent section.
        Route::post('homepage-sections/reorder', [HomepageSectionController::class, 'reorder'])->name('homepage-sections.reorder');
        Route::apiResource('homepage-sections', HomepageSectionController::class)
            ->only(['index', 'show', 'update'])
            ->parameters(['homepage-sections' => 'homepageSection']);

        Route::prefix('homepage-sections/{homepageSection}/banner-slides')->group(function () {
            Route::post('reorder', [HomepageBannerSlideController::class, 'reorder'])->name('homepage-sections.banner-slides.reorder');
            Route::get('/', [HomepageBannerSlideController::class, 'index'])->name('homepage-sections.banner-slides.index');
            Route::post('/', [HomepageBannerSlideController::class, 'store'])->name('homepage-sections.banner-slides.store');
            Route::patch('{slide}', [HomepageBannerSlideController::class, 'update'])->name('homepage-sections.banner-slides.update');
            Route::delete('{slide}', [HomepageBannerSlideController::class, 'destroy'])->name('homepage-sections.banner-slides.destroy');
        });
    });

    // -------------------- Clinic guard --------------------
    // Each clinic resource is auto-scoped to auth('clinic')->id() in its controller,
    // mirroring the Filament Clinic panel getEloquentQuery() where('clinic_id', ...).
    Route::prefix('clinic')->middleware('api.guard:clinic')->group(function () {
        // Dashboard widget — clinic-scoped stats (mirrors ClinicStatsWidget).
        Route::get('dashboard/stats', [ClinicDashboardController::class, 'stats'])->name('clinic.dashboard.stats');
        Route::get('dashboard/nav-badges', [ClinicDashboardController::class, 'navBadges'])->name('clinic.dashboard.nav-badges');

        // Full "My stats" analytics page (custom from/to range + quick periods).
        Route::get('stats', [ClinicStatsController::class, 'index'])->name('clinic.stats');

        // Lookup for sub-clinics (clinic-owned, used in the service form).
        Route::get('lookups/sub-clinics', [ClinicSubClinicController::class, 'lookup'])->name('clinic.lookups.sub-clinics');

        // Catalog & content routes — coordinator + owner only.
        // Reception is blocked because their daily job covers bookings/
        // complaints/quotes; editing services, doctors, etc. is out of
        // their lane per spec.
        Route::middleware('clinic.role:services.manage')->group(function () {
            Route::post('services/reorder', [ClinicServiceController::class, 'reorder'])->name('clinic.services.reorder');
            Route::apiResource('services', ClinicServiceController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->names('clinic.services');

            // Unified-catalog typeahead for the service form.
            Route::get('catalog-services/suggest', [\App\Http\Controllers\Api\V1\Clinic\CatalogServiceController::class, 'suggest'])->name('clinic.catalog-services.suggest');

            Route::post('import-services/analyze', [ClinicImportServicesController::class, 'analyze'])->name('clinic.import-services.analyze');
            Route::post('import-services/extract-text', [ClinicImportServicesController::class, 'extractText'])->name('clinic.import-services.extract-text');
            Route::post('import-services/confirm', [ClinicImportServicesController::class, 'confirm'])->name('clinic.import-services.confirm');
            Route::post('import-services/execute', [ClinicImportServicesController::class, 'execute'])->name('clinic.import-services.execute');
        });

        Route::middleware('clinic.role:sub_clinics.manage')->group(function () {
            Route::post('sub-clinics/reorder', [ClinicSubClinicController::class, 'reorder'])->name('clinic.sub-clinics.reorder');
            Route::apiResource('sub-clinics', ClinicSubClinicController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['sub-clinics' => 'subClinic']);
        });

        Route::middleware('clinic.role:doctors.manage')->group(function () {
            Route::apiResource('doctors', ClinicDoctorController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->names('clinic.doctors');
        });

        Route::middleware('clinic.role:packages.manage')->group(function () {
            Route::apiResource('packages', ClinicPackageController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->names('clinic.packages');
        });

        Route::middleware('clinic.role:offers.manage')->group(function () {
            Route::post('offers/{offer}/extend', [ClinicOfferController::class, 'extend'])->name('clinic.offers.extend');
            Route::apiResource('offers', ClinicOfferController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->names('clinic.offers');
        });

        Route::middleware('clinic.role:before_after.manage')->group(function () {
            Route::apiResource('before-after', ClinicBeforeAfterController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['before-after' => 'beforeAfter'])
                ->names('clinic.before-after');
        });

        // Instagram-style stories shown as a ring around the clinic logo.
        Route::middleware('clinic.role:stories.manage')->group(function () {
            Route::apiResource('stories', ClinicStoryController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->names('clinic.stories');
        });

        Route::middleware('clinic.role:category_requests.view')->group(function () {
            Route::get('category-requests', [ClinicCategoryRequestController::class, 'index'])->name('clinic.category-requests.index');
            Route::post('category-requests', [ClinicCategoryRequestController::class, 'store'])->name('clinic.category-requests.store');
        });

        // ── CRM suite ───────────────────────────────────────────────────
        // The whole operational surface (bookings/kanban, customers,
        // reminders, campaigns, price-quotes, outreach, complaints,
        // reports) sits behind one subscription gate. `crm_enabled`
        // defaults ON for every package, so this is inert until an admin
        // opts a package out — then the entire block 403s and the React
        // sidebar hides the "CRM" tab. Inner clinic.role:* groups still
        // apply per-route. Closes just before "Articles" below.
        Route::middleware('clinic.feature:crm')->group(function () {

        // Bookings — clinic can only update status / appointment / notes.
        Route::get('bookings/status-counts', [ClinicBookingController::class, 'statusCounts'])->name('clinic.bookings.status-counts');

        // Import customers/leads from a public Google Sheet (preview → commit)
        // plus saved sources for re-pulling campaign sheets.
        Route::post('bookings/imports/preview', [\App\Http\Controllers\Api\V1\Clinic\BookingImportController::class, 'preview'])->name('clinic.bookings.imports.preview');
        Route::post('bookings/imports/commit',  [\App\Http\Controllers\Api\V1\Clinic\BookingImportController::class, 'commit'])->name('clinic.bookings.imports.commit');
        // File upload variant (CSV/XLSX) — same mapping/preview/commit flow.
        Route::post('bookings/imports/file/preview', [\App\Http\Controllers\Api\V1\Clinic\BookingImportController::class, 'previewFile'])->name('clinic.bookings.imports.file.preview');
        Route::post('bookings/imports/file/commit',  [\App\Http\Controllers\Api\V1\Clinic\BookingImportController::class, 'commitFile'])->name('clinic.bookings.imports.file.commit');
        Route::get('bookings/imports/sources',  [\App\Http\Controllers\Api\V1\Clinic\BookingImportController::class, 'sources'])->name('clinic.bookings.imports.sources');
        Route::get('bookings/imports/sources/{source}',    [\App\Http\Controllers\Api\V1\Clinic\BookingImportController::class, 'showSource'])->name('clinic.bookings.imports.sources.show');
        Route::delete('bookings/imports/sources/{source}', [\App\Http\Controllers\Api\V1\Clinic\BookingImportController::class, 'destroySource'])->name('clinic.bookings.imports.sources.destroy');

        // CSV export + customisable Kanban stages (per-clinic columns).
        Route::get('bookings/export', [\App\Http\Controllers\Api\V1\Clinic\BookingExportController::class, 'export'])->name('clinic.bookings.export');
        Route::get('bookings/stages', [\App\Http\Controllers\Api\V1\Clinic\BookingStageController::class, 'index'])->name('clinic.bookings.stages.index');
        Route::post('bookings/stages', [\App\Http\Controllers\Api\V1\Clinic\BookingStageController::class, 'store'])->name('clinic.bookings.stages.store');
        Route::put('bookings/stages/reorder', [\App\Http\Controllers\Api\V1\Clinic\BookingStageController::class, 'reorder'])->name('clinic.bookings.stages.reorder');
        Route::patch('bookings/stages/{stage}', [\App\Http\Controllers\Api\V1\Clinic\BookingStageController::class, 'update'])->name('clinic.bookings.stages.update');
        Route::delete('bookings/stages/{stage}', [\App\Http\Controllers\Api\V1\Clinic\BookingStageController::class, 'destroy'])->name('clinic.bookings.stages.destroy');

        // Per-clinic Kanban card-suggestion config (toggle nudges + tune thresholds).
        Route::get('bookings/suggestion-settings', [\App\Http\Controllers\Api\V1\Clinic\BookingSuggestionSettingsController::class, 'show'])->name('clinic.bookings.suggestion-settings.show');
        Route::put('bookings/suggestion-settings', [\App\Http\Controllers\Api\V1\Clinic\BookingSuggestionSettingsController::class, 'update'])->name('clinic.bookings.suggestion-settings.update');

        // Kanban + CRM endpoints (additive, sit on top of the same Booking model).
        Route::get('bookings/kanban',       [\App\Http\Controllers\Api\V1\Clinic\BookingKanbanController::class, 'index'])->name('clinic.bookings.kanban');
        Route::get('bookings/kanban-stats', [\App\Http\Controllers\Api\V1\Clinic\BookingKanbanController::class, 'stats'])->name('clinic.bookings.kanban-stats');
        Route::get('bookings/tag-labels',   [\App\Http\Controllers\Api\V1\Clinic\BookingKanbanController::class, 'tagLabels'])->name('clinic.bookings.tag-labels');
        Route::get('bookings/assignees',    [\App\Http\Controllers\Api\V1\Clinic\BookingAssignmentController::class, 'index'])->name('clinic.bookings.assignees');
        Route::get('bookings/{booking}/detail', [\App\Http\Controllers\Api\V1\Clinic\BookingKanbanController::class, 'show'])->name('clinic.bookings.detail');

        // Per-booking activity timeline + quick actions.
        Route::get('bookings/{booking}/activities',  [\App\Http\Controllers\Api\V1\Clinic\BookingActivityController::class, 'index'])->name('clinic.bookings.activities.index');
        Route::post('bookings/{booking}/activities', [\App\Http\Controllers\Api\V1\Clinic\BookingActivityController::class, 'store'])->name('clinic.bookings.activities.store');

        // Tags (booking + customer scope share the same controller).
        Route::post('bookings/{booking}/tags',  [\App\Http\Controllers\Api\V1\Clinic\BookingTagController::class, 'store'])->name('clinic.bookings.tags.store');
        Route::delete('bookings/{booking}/tags/{scope}/{tagId}', [\App\Http\Controllers\Api\V1\Clinic\BookingTagController::class, 'destroy'])
            ->where('scope', 'booking|customer')
            ->name('clinic.bookings.tags.destroy');

        // Assignment.
        Route::patch('bookings/{booking}/assignee', [\App\Http\Controllers\Api\V1\Clinic\BookingAssignmentController::class, 'update'])->name('clinic.bookings.assignee.update');

        // Attendance — an independent signal (did the patient show up?),
        // distinct from the status enum. Idempotent confirm + revoke.
        Route::post('bookings/{booking}/attendance',   [\App\Http\Controllers\Api\V1\Clinic\BookingAttendanceController::class, 'confirm'])->name('clinic.bookings.attendance.confirm');
        Route::delete('bookings/{booking}/attendance', [\App\Http\Controllers\Api\V1\Clinic\BookingAttendanceController::class, 'revoke'])->name('clinic.bookings.attendance.revoke');

        // Service line items taken on a card (purchased services + per-customer
        // net price). Card value = sum of these. Same write gate as booking edits.
        Route::post('bookings/{booking}/services',                       [\App\Http\Controllers\Api\V1\Clinic\BookingServiceController::class, 'store'])->name('clinic.bookings.services.store');
        Route::patch('bookings/{booking}/services/{bookingService}',     [\App\Http\Controllers\Api\V1\Clinic\BookingServiceController::class, 'update'])->name('clinic.bookings.services.update');
        Route::delete('bookings/{booking}/services/{bookingService}',    [\App\Http\Controllers\Api\V1\Clinic\BookingServiceController::class, 'destroy'])->name('clinic.bookings.services.destroy');

        // Customer 360 in the Kanban side panel — keyed by phone for
        // back-compat with the existing widget.
        Route::get('customers/by-phone/{phone}', [\App\Http\Controllers\Api\V1\Clinic\CustomerProfileController::class, 'show'])
            ->where('phone', '[0-9+]+')
            ->name('clinic.customers.profile');

        // Customers Hub (phase 3) — full standalone surface.
        Route::middleware('clinic.role:customers.view')->group(function () {
            Route::get('customers',                       [\App\Http\Controllers\Api\V1\Clinic\CustomersController::class, 'index'])->name('clinic.customers.index');
            Route::get('customers/stats',                 [\App\Http\Controllers\Api\V1\Clinic\CustomersController::class, 'stats'])->name('clinic.customers.stats');
            Route::get('customers/{customer}',            [\App\Http\Controllers\Api\V1\Clinic\CustomersController::class, 'show'])->name('clinic.customers.show');
            Route::get('customers/{customer}/bookings',   [\App\Http\Controllers\Api\V1\Clinic\CustomersController::class, 'bookings'])->name('clinic.customers.bookings');
            Route::get('customers/{customer}/complaints', [\App\Http\Controllers\Api\V1\Clinic\CustomersController::class, 'complaints'])->name('clinic.customers.complaints');
            // NOTE: a customer's price-quote history is intentionally NOT exposed to the
            // clinic — only the platform admin may view it (see UserProfileController).
            Route::get('customers/{customer}/timeline',   [\App\Http\Controllers\Api\V1\Clinic\CustomersController::class, 'timeline'])->name('clinic.customers.timeline');
            // Notes (read available to all roles with customers.view; write/edit/delete gated inside the controller).
            Route::get('customers/{customer}/notes',                 [\App\Http\Controllers\Api\V1\Clinic\CustomerNotesController::class, 'index'])->name('clinic.customers.notes.index');
            Route::post('customers/{customer}/notes',                [\App\Http\Controllers\Api\V1\Clinic\CustomerNotesController::class, 'store'])->name('clinic.customers.notes.store');
            Route::patch('customers/{customer}/notes/{note}',        [\App\Http\Controllers\Api\V1\Clinic\CustomerNotesController::class, 'update'])->name('clinic.customers.notes.update');
            Route::delete('customers/{customer}/notes/{note}',       [\App\Http\Controllers\Api\V1\Clinic\CustomerNotesController::class, 'destroy'])->name('clinic.customers.notes.destroy');

            // Service value / interest reports — best-selling, most-interested,
            // who is interested in a service, and who bought it.
            Route::get('service-reports/best-selling',         [\App\Http\Controllers\Api\V1\Clinic\ServiceReportsController::class, 'bestSelling'])->name('clinic.service-reports.best-selling');
            Route::get('service-reports/most-interested',      [\App\Http\Controllers\Api\V1\Clinic\ServiceReportsController::class, 'mostInterested'])->name('clinic.service-reports.most-interested');
            Route::get('service-reports/interested-customers', [\App\Http\Controllers\Api\V1\Clinic\ServiceReportsController::class, 'interestedCustomers'])->name('clinic.service-reports.interested-customers');
            Route::get('service-reports/buyers',               [\App\Http\Controllers\Api\V1\Clinic\ServiceReportsController::class, 'serviceBuyers'])->name('clinic.service-reports.buyers');
        });
        Route::middleware('clinic.role:customers.manage')->group(function () {
            Route::patch('customers/{customer}', [\App\Http\Controllers\Api\V1\Clinic\CustomersController::class, 'update'])->name('clinic.customers.update');

            // Customer interested-services list (intent, not purchases).
            Route::post('customers/{customer}/interested-services',             [\App\Http\Controllers\Api\V1\Clinic\CustomerInterestedServiceController::class, 'store'])->name('clinic.customers.interested.store');
            Route::delete('customers/{customer}/interested-services/{service}', [\App\Http\Controllers\Api\V1\Clinic\CustomerInterestedServiceController::class, 'destroy'])->name('clinic.customers.interested.destroy');
        });

        // Contact reminders — "call this patient at X". A scheduler rings
        // the clinic bell when remind_at passes. Whole-clinic audience.
        Route::middleware('clinic.role:reminders.view')->group(function () {
            Route::get('reminders', [\App\Http\Controllers\Api\V1\Clinic\CustomerReminderController::class, 'index'])->name('clinic.reminders.index');
        });
        Route::middleware('clinic.role:reminders.create')->group(function () {
            Route::post('reminders', [\App\Http\Controllers\Api\V1\Clinic\CustomerReminderController::class, 'store'])->name('clinic.reminders.store');
        });
        Route::middleware('clinic.role:reminders.manage')->group(function () {
            Route::post('reminders/{reminder}/complete', [\App\Http\Controllers\Api\V1\Clinic\CustomerReminderController::class, 'complete'])->name('clinic.reminders.complete');
            Route::post('reminders/{reminder}/cancel', [\App\Http\Controllers\Api\V1\Clinic\CustomerReminderController::class, 'cancel'])->name('clinic.reminders.cancel');
        });

        // Patient campaigns — segment a clinic's own customers + manual
        // WhatsApp outreach with per-recipient tracking.
        Route::middleware('clinic.role:campaigns.view')->group(function () {
            Route::get('campaigns', [\App\Http\Controllers\Api\V1\Clinic\CampaignController::class, 'index'])->name('clinic.campaigns.index');
            Route::post('campaigns/preview', [\App\Http\Controllers\Api\V1\Clinic\CampaignController::class, 'preview'])->name('clinic.campaigns.preview');
            Route::get('campaigns/{campaign}', [\App\Http\Controllers\Api\V1\Clinic\CampaignController::class, 'show'])->name('clinic.campaigns.show');
            Route::get('campaigns/{campaign}/recipients', [\App\Http\Controllers\Api\V1\Clinic\CampaignController::class, 'recipients'])->name('clinic.campaigns.recipients');
        });
        Route::middleware('clinic.role:campaigns.manage')->group(function () {
            Route::post('campaigns', [\App\Http\Controllers\Api\V1\Clinic\CampaignController::class, 'store'])->name('clinic.campaigns.store');
            Route::post('campaigns/{campaign}/recipients/{recipient}/mark', [\App\Http\Controllers\Api\V1\Clinic\CampaignController::class, 'markRecipient'])->name('clinic.campaigns.mark');
            Route::delete('campaigns/{campaign}', [\App\Http\Controllers\Api\V1\Clinic\CampaignController::class, 'destroy'])->name('clinic.campaigns.destroy');
        });

        Route::apiResource('bookings', ClinicBookingController::class)
            ->only(['index', 'store', 'show', 'update'])
            ->names('clinic.bookings');

        // Broadcast price quote requests — clinic sees requests targeting its
        // city and posts one reply (public/private) per request.
        Route::get('price-quotes', [ClinicPriceQuoteRequestController::class, 'index'])->name('clinic.price-quotes.index');
        // Reply gate state + request to be re-enabled after an admin disable.
        Route::get('price-quotes/access', [ClinicPriceQuoteRequestController::class, 'access'])->name('clinic.price-quotes.access');
        Route::post('price-quotes/access/request', [ClinicPriceQuoteRequestController::class, 'requestAccess'])->name('clinic.price-quotes.access.request');
        Route::post('price-quotes/{priceQuote}/reply', [ClinicPriceQuoteRequestController::class, 'reply'])->name('clinic.price-quotes.reply');

        // Records a complex's outreach to a customer (WhatsApp/call) → stats + visibility.
        Route::post('outreach', [ClinicOutreachController::class, 'store'])->name('clinic.outreach');

        // Customer complaints filed AGAINST this complex. List is
        // read-only; the new `reply` endpoint lets the complex respond
        // once per complaint (the customer-facing view shows the team
        // member's name + role per spec).
        Route::get('complaints', [ClinicComplaintController::class, 'index'])->name('clinic.complaints.index');
        Route::middleware('clinic.role:complaints.reply')->group(function () {
            Route::post('complaints/{complaint}/reply', [ClinicComplaintController::class, 'reply'])->name('clinic.complaints.reply');
        });

        // Reports raised BY the complex to platform admins (the old
        // "clinic complaint" surface, redesigned with proper report types:
        // technical issue, abusive customer, fake review, billing, etc.).
        Route::get('reports', [ClinicReportController::class, 'index'])->name('clinic.reports.index');
        Route::post('reports', [ClinicReportController::class, 'store'])->name('clinic.reports.store');

        }); // end clinic.feature:crm group

        // Articles — coordinator + owner only.
        Route::middleware('clinic.role:articles.manage')->group(function () {
            Route::post('articles/generate-ai', [ClinicArticleController::class, 'generateAi'])->name('clinic.articles.generate-ai');
            Route::apiResource('articles', ClinicArticleController::class)->names('clinic.articles');
        });

        // Profile read — anyone authenticated; write — owner only.
        Route::get('profile', [ClinicProfileController::class, 'show'])->name('clinic.profile.show');
        Route::get('reviews', [ClinicProfileController::class, 'reviews'])->name('clinic.reviews');
        Route::middleware('clinic.role:profile.manage')->group(function () {
            Route::patch('profile', [ClinicProfileController::class, 'update'])->name('clinic.profile.update');
            Route::post('profile/extract-coords', [ClinicProfileController::class, 'extractCoords'])->name('clinic.profile.extract-coords');
            Route::post('profile/sync-reviews', [ClinicProfileController::class, 'syncReviews'])->name('clinic.profile.sync-reviews');
        });

        // Marketing tracking pixels — owner only. Read to view the form,
        // manage to save the draft + request activation.
        Route::middleware('clinic.role:tracking.view')->group(function () {
            Route::get('tracking', [\App\Http\Controllers\Api\V1\Clinic\TrackingController::class, 'show'])->name('clinic.tracking.show');
        });
        Route::middleware('clinic.role:tracking.manage')->group(function () {
            Route::put('tracking', [\App\Http\Controllers\Api\V1\Clinic\TrackingController::class, 'update'])->name('clinic.tracking.update');
            Route::post('tracking/request', [\App\Http\Controllers\Api\V1\Clinic\TrackingController::class, 'requestActivation'])->name('clinic.tracking.request');
        });

        // Cart feature — owner only. View status; request activation +
        // toggle the storefront show/hide switch once active.
        Route::middleware('clinic.role:cart.view')->group(function () {
            Route::get('cart', [\App\Http\Controllers\Api\V1\Clinic\CartController::class, 'show'])->name('clinic.cart.show');
        });
        Route::middleware('clinic.role:cart.manage')->group(function () {
            Route::put('cart', [\App\Http\Controllers\Api\V1\Clinic\CartController::class, 'update'])->name('clinic.cart.update');
            Route::post('cart/request', [\App\Http\Controllers\Api\V1\Clinic\CartController::class, 'requestActivation'])->name('clinic.cart.request');
        });

        // Abandoned-cart follow-up — view list (cart_leads.view), log outreach
        // + convert to a Kanban booking draft (cart_leads.contact).
        Route::middleware('clinic.role:cart_leads.view')->group(function () {
            Route::get('abandoned-carts', [\App\Http\Controllers\Api\V1\Clinic\AbandonedCartController::class, 'index'])->name('clinic.abandoned-carts.index');
        });
        Route::middleware('clinic.role:cart_leads.contact')->group(function () {
            Route::post('abandoned-carts/{user}/contact', [\App\Http\Controllers\Api\V1\Clinic\AbandonedCartController::class, 'contact'])->name('clinic.abandoned-carts.contact');
            Route::post('abandoned-carts/{user}/convert', [\App\Http\Controllers\Api\V1\Clinic\AbandonedCartController::class, 'convert'])->name('clinic.abandoned-carts.convert');
        });

        // Subscription — owner only (sensitive financial data per spec).
        Route::middleware('clinic.role:subscription.view')->group(function () {
            Route::get('subscription', [ClinicProfileController::class, 'subscription'])->name('clinic.subscription');
        });

        // Working hours — coordinator + owner (catalog management).
        Route::middleware('clinic.role:services.manage')->group(function () {
            Route::get('working-hours', [ClinicWorkingHoursController::class, 'index'])->name('clinic.working-hours.index');
            Route::put('working-hours', [ClinicWorkingHoursController::class, 'update'])->name('clinic.working-hours.update');
        });

        // Page Builder — coordinator + owner.
        Route::middleware('clinic.role:page_builder.manage')->group(function () {
            Route::get('page-sections', [ClinicPageSectionController::class, 'index'])->name('clinic.page-sections.index');
            Route::post('page-sections/reorder', [ClinicPageSectionController::class, 'reorder'])->name('clinic.page-sections.reorder');
            Route::patch('page-sections/{section}', [ClinicPageSectionController::class, 'update'])
                ->name('clinic.page-sections.update');
        });

        // Landing pages — a complex builds profile-style pages for itself
        // (type forced to `clinic`). Read to browse/preview; manage to
        // create/edit/submit. Each page is vetted once by the platform admin
        // (Access Center) before its first public appearance.
        Route::middleware('clinic.role:landing_pages.view')->group(function () {
            Route::get('landing-pages', [\App\Http\Controllers\Api\V1\Clinic\LandingPageController::class, 'index'])->name('clinic.landing-pages.index');
            Route::get('landing-pages/{landing_page}', [\App\Http\Controllers\Api\V1\Clinic\LandingPageController::class, 'show'])->name('clinic.landing-pages.show');
            Route::get('landing-pages/{landing_page}/blocks', [\App\Http\Controllers\Api\V1\Clinic\LandingPageBlockController::class, 'index'])->name('clinic.landing-pages.blocks.index');
            Route::get('landing-pages/{landing_page}/stats', [\App\Http\Controllers\Api\V1\Clinic\LandingPageController::class, 'stats'])->name('clinic.landing-pages.stats');
            Route::get('landing-pages/{landing_page}/customers', [\App\Http\Controllers\Api\V1\Clinic\LandingPageController::class, 'customers'])->name('clinic.landing-pages.customers');
        });
        Route::middleware('clinic.role:landing_pages.manage')->group(function () {
            Route::post('landing-pages', [\App\Http\Controllers\Api\V1\Clinic\LandingPageController::class, 'store'])->name('clinic.landing-pages.store');
            Route::patch('landing-pages/{landing_page}', [\App\Http\Controllers\Api\V1\Clinic\LandingPageController::class, 'update'])->name('clinic.landing-pages.update');
            Route::delete('landing-pages/{landing_page}', [\App\Http\Controllers\Api\V1\Clinic\LandingPageController::class, 'destroy'])->name('clinic.landing-pages.destroy');
            Route::post('landing-pages/{landing_page}/submit', [\App\Http\Controllers\Api\V1\Clinic\LandingPageController::class, 'submit'])->name('clinic.landing-pages.submit');

            Route::post('landing-pages/{landing_page}/blocks/reorder', [\App\Http\Controllers\Api\V1\Clinic\LandingPageBlockController::class, 'reorder'])->name('clinic.landing-pages.blocks.reorder');
            Route::post('landing-pages/{landing_page}/blocks', [\App\Http\Controllers\Api\V1\Clinic\LandingPageBlockController::class, 'store'])->name('clinic.landing-pages.blocks.store');
            Route::patch('landing-pages/{landing_page}/blocks/{block}', [\App\Http\Controllers\Api\V1\Clinic\LandingPageBlockController::class, 'update'])->name('clinic.landing-pages.blocks.update');
            Route::delete('landing-pages/{landing_page}/blocks/{block}', [\App\Http\Controllers\Api\V1\Clinic\LandingPageBlockController::class, 'destroy'])->name('clinic.landing-pages.blocks.destroy');
        });

        // Team management — owner only.
        Route::middleware('clinic.role:team.manage')->group(function () {
            Route::get('team', [\App\Http\Controllers\Api\V1\Clinic\TeamController::class, 'index'])->name('clinic.team.index');
            Route::post('team', [\App\Http\Controllers\Api\V1\Clinic\TeamController::class, 'store'])->name('clinic.team.store');
            Route::patch('team/{member}', [\App\Http\Controllers\Api\V1\Clinic\TeamController::class, 'update'])->name('clinic.team.update');
            Route::post('team/{member}/regenerate-password', [\App\Http\Controllers\Api\V1\Clinic\TeamController::class, 'regeneratePassword'])->name('clinic.team.regenerate-password');
            Route::delete('team/{member}', [\App\Http\Controllers\Api\V1\Clinic\TeamController::class, 'destroy'])->name('clinic.team.destroy');
        });

        // Team activity log — owner only.
        Route::middleware('clinic.role:team_activity.view')->group(function () {
            Route::get('team-activity', [\App\Http\Controllers\Api\V1\Clinic\TeamActivityController::class, 'index'])->name('clinic.team-activity.index');
        });

        // Cashback rewards — gated by the rewards package feature. Rule
        // config + issued-voucher list + manual grant + reception redeem.
        // Isolation via query filter + RewardVoucherPolicy.
        Route::middleware('clinic.feature:rewards')->group(function () {
            // Configure the grant rule + issue manual grants — owner/coordinator.
            Route::middleware('clinic.role:rewards.manage')->group(function () {
                Route::get('rewards/rule',  [\App\Http\Controllers\Api\V1\Clinic\ClinicRewardRuleController::class, 'show'])->name('clinic.rewards.rule.show');
                Route::put('rewards/rule',  [\App\Http\Controllers\Api\V1\Clinic\ClinicRewardRuleController::class, 'update'])->name('clinic.rewards.rule.update');
                Route::post('rewards',      [\App\Http\Controllers\Api\V1\Clinic\ClinicRewardVoucherController::class, 'store'])->name('clinic.rewards.store');
            });
            // View the issued vouchers — reception and up.
            Route::middleware('clinic.role:rewards.view')->group(function () {
                Route::get('rewards', [\App\Http\Controllers\Api\V1\Clinic\ClinicRewardVoucherController::class, 'index'])->name('clinic.rewards.index');
            });
            // Redeem at the desk — reception and up.
            Route::middleware('clinic.role:rewards.redeem')->group(function () {
                Route::post('rewards/{voucher}/redeem', [\App\Http\Controllers\Api\V1\Clinic\ClinicRewardVoucherController::class, 'redeem'])->name('clinic.rewards.redeem');
            });
        });

        // Verified reviews — the clinic's reviews + its public reply.
        // Always-on (no feature gate); isolation via policy. Reply ADDS
        // only — it never alters or hides the review (non-coercive).
        Route::get('verified-reviews', [\App\Http\Controllers\Api\V1\Clinic\ClinicVerifiedReviewController::class, 'index'])->name('clinic.verified-reviews.index');
        Route::post('verified-reviews/{review}/reply', [\App\Http\Controllers\Api\V1\Clinic\ClinicVerifiedReviewController::class, 'reply'])->name('clinic.verified-reviews.reply');
        // Flag spam/abuse for admin review (does NOT hide the review).
        Route::post('verified-reviews/{review}/report', [\App\Http\Controllers\Api\V1\Clinic\ClinicVerifiedReviewController::class, 'report'])->name('clinic.verified-reviews.report');
    });
});
