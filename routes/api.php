<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Admin\BookingController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\CityController;
use App\Http\Controllers\Api\V1\Admin\ClinicController;
use App\Http\Controllers\Api\V1\Admin\ComplaintController;
use App\Http\Controllers\Api\V1\Admin\SalesLeadController;
use App\Http\Controllers\Api\V1\Admin\ServiceController;
use App\Http\Controllers\Api\V1\Admin\UserController;
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
    });

    // -------------------- Clinic guard --------------------
    Route::prefix('clinic')->middleware('api.guard:clinic')->group(function () {
        // wired in later phases
    });
});
