<?php

use App\Http\Controllers\Api\V1\Admin\CityController;
use App\Http\Controllers\Api\V1\Shared\AuthController;
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
    });

    // -------------------- Admin guard --------------------
    Route::prefix('admin')->middleware('api.guard:admin')->group(function () {
        Route::apiResource('cities', CityController::class);
    });

    // -------------------- Clinic guard --------------------
    Route::prefix('clinic')->middleware('api.guard:clinic')->group(function () {
        // wired in later phases
    });
});
