<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\BookingController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\CityController;
use App\Http\Controllers\Api\V1\Admin\ClinicController;
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
use App\Http\Controllers\Api\V1\Clinic\BookingController as ClinicBookingController;
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

        // Bookings — clinic can only update status / appointment / notes.
        Route::get('bookings/status-counts', [ClinicBookingController::class, 'statusCounts'])->name('clinic.bookings.status-counts');
        Route::apiResource('bookings', ClinicBookingController::class)
            ->only(['index', 'show', 'update'])
            ->names('clinic.bookings');

        // Price quote requests — clinic can update status + reply.
        Route::apiResource('price-quotes', ClinicPriceQuoteRequestController::class)
            ->only(['index', 'show', 'update'])
            ->parameters(['price-quotes' => 'priceQuote'])
            ->names('clinic.price-quotes');

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
