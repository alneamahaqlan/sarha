<?php

use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Public\AccountController;
use App\Http\Controllers\Public\ArticleController;
use App\Http\Controllers\Public\ClinicController;
use App\Http\Controllers\Public\ClinicRegistrationController;
use App\Http\Controllers\Public\CompareController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// React admin SPA shell — all /app/* routes return the same HTML, React Router handles routing.
Route::get('/app/{any?}', fn () => view('react-admin'))
    ->where('any', '.*')
    ->name('react-admin');

// SEO
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/robots.txt', [SitemapController::class, 'robots']);

// Impersonation
Route::post('/admin/impersonate/{clinic}', [\App\Http\Controllers\ImpersonationController::class, 'start'])
    ->middleware('auth:admin')->name('impersonate.start');
Route::post('/impersonate/stop', [\App\Http\Controllers\ImpersonationController::class, 'stop'])
    ->middleware('auth:clinic')->name('impersonate.stop');

// Language switcher
Route::get('/lang/{locale}', [LanguageController::class, 'switch'])
    ->whereIn('locale', ['ar', 'en'])
    ->name('lang.switch');

// Public website
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/compare', [CompareController::class, 'index'])->name('compare');
Route::get('/clinic/{slug}', [ClinicController::class, 'show'])->name('clinic.show');
Route::get('/clinic/{slug}/book', [ClinicController::class, 'bookingForm'])->name('clinic.book.form');
Route::post('/clinic/{slug}/book', [ClinicController::class, 'book'])->name('clinic.book');
Route::post('/clinic/{slug}/quote', [ClinicController::class, 'priceQuote'])->name('clinic.quote');
Route::get('/booking/{reference}', [ClinicController::class, 'bookingConfirmation'])
    ->where('reference', '[A-Z0-9-]+')
    ->name('booking.confirmation');

// Standalone article page (SEO) — published articles of publicly-visible clinics.
Route::get('/article/{slug}', [ArticleController::class, 'show'])->name('article.show');

// Public "List your complex" — creates a SalesLead for the admin pipeline.
Route::get('/register-clinic', [ClinicRegistrationController::class, 'show'])->name('clinic.register');
Route::post('/register-clinic', [ClinicRegistrationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('clinic.register.submit');

// Customer OTP auth
Route::middleware('guest:web')->group(function () {
    Route::get('/login', [OtpController::class, 'showLogin'])->name('login');
    Route::post('/login/send-otp', [OtpController::class, 'sendOtp'])->name('login.otp');
    Route::post('/login/verify', [OtpController::class, 'verifyOtp'])->name('login.verify');
});
Route::post('/logout', [OtpController::class, 'logout'])->name('logout');

// Customer account area
Route::middleware('auth:web')->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
    Route::get('/account/bookings', [AccountController::class, 'bookings'])->name('account.bookings');
    Route::get('/account/favorites', [AccountController::class, 'favorites'])->name('account.favorites');
    Route::post('/favorites/{clinic:slug}/toggle', [AccountController::class, 'toggleFavorite'])->name('favorites.toggle');
});
