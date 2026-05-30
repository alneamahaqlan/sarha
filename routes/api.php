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
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\UserProfileController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Clinic\ArticleController as ClinicArticleController;
use App\Http\Controllers\Api\V1\Clinic\BeforeAfterController as ClinicBeforeAfterController;
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

        Route::apiResource('cities', CityController::class);

        Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
        Route::apiResource('categories', CategoryController::class);

        // Subscription packages catalogue — full CRUD for the super-admin.
        // Deletion is rejected at controller level if any clinic is on the package.
        Route::apiResource('subscription-packages', \App\Http\Controllers\Api\V1\Admin\SubscriptionPackageController::class);

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
        Route::post('bookings/{booking_trashed}/restore', [BookingController::class, 'restore'])->name('bookings.restore');
        Route::delete('bookings/{booking_trashed}/force', [BookingController::class, 'forceDestroy'])->name('bookings.force-destroy');
        Route::apiResource('bookings', BookingController::class);

        // Complaint state-transition actions — each delegates to ComplaintService.
        Route::post('complaints/{complaint}/mark-in-review', [ComplaintController::class, 'markInReview'])->name('complaints.mark-in-review');
        Route::post('complaints/{complaint}/resolve', [ComplaintController::class, 'resolve'])->name('complaints.resolve');
        Route::post('complaints/{complaint}/reject', [ComplaintController::class, 'reject'])->name('complaints.reject');
        Route::post('complaints/{complaint}/notify-clinic', [ComplaintController::class, 'notifyClinic'])->name('complaints.notify-clinic');
        Route::apiResource('complaints', ComplaintController::class);

        // SalesLead — convert delegates to SalesLeadService (DB::transaction).
        Route::post('sales-leads/{salesLead}/convert', [SalesLeadController::class, 'convert'])->name('sales-leads.convert');
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

            Route::post('import-services/analyze', [ClinicImportServicesController::class, 'analyze'])->name('clinic.import-services.analyze');
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

        Route::middleware('clinic.role:category_requests.view')->group(function () {
            Route::get('category-requests', [ClinicCategoryRequestController::class, 'index'])->name('clinic.category-requests.index');
            Route::post('category-requests', [ClinicCategoryRequestController::class, 'store'])->name('clinic.category-requests.store');
        });

        // Bookings — clinic can only update status / appointment / notes.
        Route::get('bookings/status-counts', [ClinicBookingController::class, 'statusCounts'])->name('clinic.bookings.status-counts');

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

        // Customer 360 — keyed by phone, clinic-scoped.
        Route::get('customers/by-phone/{phone}', [\App\Http\Controllers\Api\V1\Clinic\CustomerProfileController::class, 'show'])
            ->where('phone', '[0-9+]+')
            ->name('clinic.customers.profile');

        Route::apiResource('bookings', ClinicBookingController::class)
            ->only(['index', 'store', 'show', 'update'])
            ->names('clinic.bookings');

        // Broadcast price quote requests — clinic sees requests targeting its
        // city and posts one reply (public/private) per request.
        Route::get('price-quotes', [ClinicPriceQuoteRequestController::class, 'index'])->name('clinic.price-quotes.index');
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
    });
});
