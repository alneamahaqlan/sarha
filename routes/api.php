<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
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
use App\Http\Controllers\Api\V1\Clinic\ArticleController as ClinicArticleController;
use App\Http\Controllers\Api\V1\Clinic\BookingController as ClinicBookingController;
use App\Http\Controllers\Api\V1\Clinic\CustomCategoryController as ClinicCustomCategoryController;
use App\Http\Controllers\Api\V1\Clinic\ImportServicesController as ClinicImportServicesController;
use App\Http\Controllers\Api\V1\Clinic\PriceQuoteRequestController as ClinicPriceQuoteRequestController;
use App\Http\Controllers\Api\V1\Clinic\ProfileController as ClinicProfileController;
use App\Http\Controllers\Api\V1\Clinic\ServiceController as ClinicServiceController;
use App\Http\Controllers\Api\V1\Shared\AuthController;
use App\Http\Controllers\Api\V1\Shared\LookupController;
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
    });

    // -------------------- Admin guard --------------------
    Route::prefix('admin')->middleware('api.guard:admin')->group(function () {
        Route::apiResource('cities', CityController::class);

        Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
        Route::apiResource('categories', CategoryController::class);

        // UserResource has no Delete in Filament — restrict to index/show/store/update only.
        Route::apiResource('users', UserController::class)->except(['destroy']);

        // Admin (panel administrators) — route param renamed to avoid collision with the
        // 'admin' segment that the api.guard middleware uses.
        Route::apiResource('admins', AdminController::class)->parameter('admins', 'admin_user');

        Route::apiResource('services', ServiceController::class);

        // Booking — restore + forceDestroy need to resolve trashed rows, so we bind
        // the {booking_trashed} param with withTrashed() instead of default scope.
        Route::bind('booking_trashed', fn ($id) => Booking::withTrashed()->findOrFail($id));
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
        Route::post('clinics/{clinic}/approve', [ClinicController::class, 'approve'])->name('clinics.approve');
        Route::post('clinics/{clinic}/reject', [ClinicController::class, 'reject'])->name('clinics.reject');
        Route::post('clinics/{clinic}/activate', [ClinicController::class, 'activate'])->name('clinics.activate');
        Route::post('clinics/{clinic}/suspend', [ClinicController::class, 'suspend'])->name('clinics.suspend');
        Route::post('clinics/{clinic}/extend', [ClinicController::class, 'extend'])->name('clinics.extend');
        Route::post('clinics/{clinic}/impersonate', [ClinicController::class, 'impersonate'])->name('clinics.impersonate');
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
        // Services (clinic-owned) + reorder.
        Route::post('services/reorder', [ClinicServiceController::class, 'reorder'])->name('clinic.services.reorder');
        Route::apiResource('services', ClinicServiceController::class)->only(['index', 'store', 'update', 'destroy']);

        // Custom categories + reorder + delete guard.
        Route::post('custom-categories/reorder', [ClinicCustomCategoryController::class, 'reorder'])->name('clinic.custom-categories.reorder');
        Route::apiResource('custom-categories', ClinicCustomCategoryController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['custom-categories' => 'customCategory']);

        // Bookings — clinic can only update status / appointment / notes.
        Route::apiResource('bookings', ClinicBookingController::class)->only(['index', 'show', 'update']);

        // Price quote requests — clinic can update status + reply.
        Route::apiResource('price-quotes', ClinicPriceQuoteRequestController::class)
            ->only(['index', 'show', 'update'])
            ->parameters(['price-quotes' => 'priceQuote']);

        // Articles — CRUD + AI generate (excerpt/article). ArticleObserver enforces
        // the basic-plan monthly publish limit; preserved as-is via Model events.
        Route::post('articles/generate-ai', [ClinicArticleController::class, 'generateAi'])->name('clinic.articles.generate-ai');
        Route::apiResource('articles', ClinicArticleController::class);

        // Profile — self-update of the authenticated clinic.
        Route::get('profile', [ClinicProfileController::class, 'show'])->name('clinic.profile.show');
        Route::patch('profile', [ClinicProfileController::class, 'update'])->name('clinic.profile.update');

        // Import services — 2-step flow (analyze CSV → execute import) using
        // the same ImportServicesService that the Filament page now calls.
        Route::post('import-services/analyze', [ClinicImportServicesController::class, 'analyze'])->name('clinic.import-services.analyze');
        Route::post('import-services/execute', [ClinicImportServicesController::class, 'execute'])->name('clinic.import-services.execute');
    });
});
