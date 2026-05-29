<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
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
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Clinic\ArticleController as ClinicArticleController;
use App\Http\Controllers\Api\V1\Clinic\BeforeAfterController as ClinicBeforeAfterController;
use App\Http\Controllers\Api\V1\Clinic\BookingController as ClinicBookingController;
use App\Http\Controllers\Api\V1\Clinic\CategoryRequestController as ClinicCategoryRequestController;
use App\Http\Controllers\Api\V1\Clinic\ComplaintController as ClinicComplaintController;
use App\Http\Controllers\Api\V1\Clinic\ReportController as ClinicReportController;
use App\Http\Controllers\Api\V1\Admin\ClinicReportController as AdminClinicReportController;
use App\Http\Controllers\Api\V1\Clinic\DoctorController as ClinicDoctorController;
use App\Http\Controllers\Api\V1\Clinic\OutreachController as ClinicOutreachController;
use App\Http\Controllers\Api\V1\Clinic\PackageController as ClinicPackageController;
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

        // UserResource has no Delete in Filament — restrict to index/show/store/update only.
        Route::apiResource('users', UserController::class)->except(['destroy']);

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

        // Services (clinic-owned) + reorder.
        Route::post('services/reorder', [ClinicServiceController::class, 'reorder'])->name('clinic.services.reorder');
        // Explicit name prefix — the same path lives under /admin/services with the
        // same auto-generated names, so route:cache refuses to serialize duplicates.
        Route::apiResource('services', ClinicServiceController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->names('clinic.services');

        // Sub-clinics (the "clinic" middle level) + reorder + delete guard.
        Route::post('sub-clinics/reorder', [ClinicSubClinicController::class, 'reorder'])->name('clinic.sub-clinics.reorder');
        Route::apiResource('sub-clinics', ClinicSubClinicController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['sub-clinics' => 'subClinic']);

        // Doctors (showcase) — clinic-owned CRUD.
        Route::apiResource('doctors', ClinicDoctorController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->names('clinic.doctors');

        // Packages (bundles of services) — clinic-owned CRUD.
        Route::apiResource('packages', ClinicPackageController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->names('clinic.packages');

        // Before/after photo gallery — clinic-owned CRUD.
        Route::apiResource('before-after', ClinicBeforeAfterController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['before-after' => 'beforeAfter'])
            ->names('clinic.before-after');

        // Specialty (category) requests submitted to the admins.
        Route::get('category-requests', [ClinicCategoryRequestController::class, 'index'])->name('clinic.category-requests.index');
        Route::post('category-requests', [ClinicCategoryRequestController::class, 'store'])->name('clinic.category-requests.store');

        // Bookings — clinic can only update status / appointment / notes.
        Route::get('bookings/status-counts', [ClinicBookingController::class, 'statusCounts'])->name('clinic.bookings.status-counts');
        Route::apiResource('bookings', ClinicBookingController::class)
            ->only(['index', 'show', 'update'])
            ->names('clinic.bookings');

        // Broadcast price quote requests — clinic sees requests targeting its
        // city and posts one reply (public/private) per request.
        Route::get('price-quotes', [ClinicPriceQuoteRequestController::class, 'index'])->name('clinic.price-quotes.index');
        Route::post('price-quotes/{priceQuote}/reply', [ClinicPriceQuoteRequestController::class, 'reply'])->name('clinic.price-quotes.reply');

        // Records a complex's outreach to a customer (WhatsApp/call) → stats + visibility.
        Route::post('outreach', [ClinicOutreachController::class, 'store'])->name('clinic.outreach');

        // Customer complaints filed AGAINST this complex — read-only here
        // (the complex sees what was raised against them; admins resolve).
        Route::get('complaints', [ClinicComplaintController::class, 'index'])->name('clinic.complaints.index');

        // Reports raised BY the complex to platform admins (the old
        // "clinic complaint" surface, redesigned with proper report types:
        // technical issue, abusive customer, fake review, billing, etc.).
        Route::get('reports', [ClinicReportController::class, 'index'])->name('clinic.reports.index');
        Route::post('reports', [ClinicReportController::class, 'store'])->name('clinic.reports.store');

        // Articles — CRUD + AI generate (excerpt/article). ArticleObserver enforces
        // the basic-plan monthly publish limit; preserved as-is via Model events.
        Route::post('articles/generate-ai', [ClinicArticleController::class, 'generateAi'])->name('clinic.articles.generate-ai');
        Route::apiResource('articles', ClinicArticleController::class)->names('clinic.articles');

        // Profile — self-update of the authenticated clinic.
        Route::get('profile', [ClinicProfileController::class, 'show'])->name('clinic.profile.show');
        Route::patch('profile', [ClinicProfileController::class, 'update'])->name('clinic.profile.update');
        Route::post('profile/extract-coords', [ClinicProfileController::class, 'extractCoords'])->name('clinic.profile.extract-coords');
        Route::post('profile/sync-reviews', [ClinicProfileController::class, 'syncReviews'])->name('clinic.profile.sync-reviews');
        Route::get('reviews', [ClinicProfileController::class, 'reviews'])->name('clinic.reviews');

        // Subscription — display-only current plan + platform plan prices.
        Route::get('subscription', [ClinicProfileController::class, 'subscription'])->name('clinic.subscription');

        // Working hours — 7-day schedule (creates defaults on first read).
        Route::get('working-hours', [ClinicWorkingHoursController::class, 'index'])->name('clinic.working-hours.index');
        Route::put('working-hours', [ClinicWorkingHoursController::class, 'update'])->name('clinic.working-hours.update');

        // Import services — 2-step flow (analyze CSV → execute import) using
        // the same ImportServicesService that the Filament page now calls.
        Route::post('import-services/analyze', [ClinicImportServicesController::class, 'analyze'])->name('clinic.import-services.analyze');
        Route::post('import-services/execute', [ClinicImportServicesController::class, 'execute'])->name('clinic.import-services.execute');
    });
});
