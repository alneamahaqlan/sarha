<?php

use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Public\AccountController;
use App\Http\Controllers\Public\ArticleController;
use App\Http\Controllers\Public\ClinicController;
use App\Http\Controllers\Public\ClinicRegistrationController;
use App\Http\Controllers\Public\BeforeAfterController;
use App\Http\Controllers\Public\CompareController;
use App\Http\Controllers\Public\DoctorController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\OfferController;
use App\Http\Controllers\Public\PackageController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\QuoteController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\ServiceController;
use App\Http\Controllers\Public\SubClinicController;
use App\Http\Controllers\Public\TrackingController;
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
Route::get('/search/suggest', [SearchController::class, 'suggest'])->middleware('throttle:60,1')->name('search.suggest');
Route::get('/compare', [CompareController::class, 'index'])->name('compare');

// Fire-and-forget click tracking for clinic action buttons (sendBeacon, CSRF-excluded).
Route::post('/track/click', [TrackingController::class, 'click'])
    ->middleware('throttle:120,1')
    ->name('track.click');
Route::get('/clinic/{slug}', [ClinicController::class, 'show'])->name('clinic.show');
Route::get('/clinic/{slug}/book', [ClinicController::class, 'bookingForm'])->name('clinic.book.form');
Route::post('/clinic/{slug}/book', [ClinicController::class, 'book'])->name('clinic.book');
Route::post('/clinic/{slug}/book/verify', [ClinicController::class, 'bookVerify'])->name('clinic.book.verify');
Route::post('/clinic/{slug}/quote', [ClinicController::class, 'priceQuote'])->name('clinic.quote');
Route::get('/booking/{reference}', [ClinicController::class, 'bookingConfirmation'])
    ->where('reference', '[A-Z0-9-]+')
    ->name('booking.confirmation');

// Standalone detail pages, scoped under the complex slug for context.
// Clicking an offer lands on its detail page (not booking) — the page's
// own CTA deep-links to booking.
Route::get('/clinic/{slug}/offer/{offer}', [OfferController::class, 'show'])->name('offer.show');
Route::get('/clinic/{slug}/service/{service}', [ServiceController::class, 'show'])->name('service.show');
Route::get('/clinic/{slug}/package/{package}', [PackageController::class, 'show'])->name('package.show');
Route::get('/clinic/{slug}/c/{subClinic}', [SubClinicController::class, 'show'])->name('subclinic.show');
Route::get('/clinic/{slug}/doctor/{doctor}', [DoctorController::class, 'show'])->name('doctor.show');
Route::get('/clinic/{slug}/before-after/{photo}', [BeforeAfterController::class, 'show'])->name('before-after.show');

// Broadcast price-quote requests (not tied to a single clinic).
Route::get('/quotes', [QuoteController::class, 'board'])->name('quotes.board');
Route::get('/quotes/new', [QuoteController::class, 'requestForm'])->name('quotes.request');
Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
Route::post('/quotes/verify', [QuoteController::class, 'storeVerify'])->name('quotes.verify');
Route::get('/quotes/{quoteRequest}', [QuoteController::class, 'show'])->whereNumber('quoteRequest')->name('quotes.show');

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
    Route::get('/account/quotes', [AccountController::class, 'quotes'])->name('account.quotes');
    Route::get('/account/complaints', [AccountController::class, 'complaints'])->name('account.complaints');
    Route::post('/account/complaints', [AccountController::class, 'storeComplaint'])->name('account.complaints.store');
    // Customer-side platform reports — separate from complaints because they
    // route to admin only (bugs / suggestions / abuse), not to a clinic inbox.
    Route::get('/account/reports', [AccountController::class, 'reports'])->name('account.reports');
    Route::post('/account/reports', [AccountController::class, 'storeReport'])->name('account.reports.store');
    Route::post('/favorites/{clinic:slug}/toggle', [AccountController::class, 'toggleFavorite'])->name('favorites.toggle');
    // Saved services + offers (polymorphic) — type + id in the body.
    Route::post('/saved/toggle', [AccountController::class, 'toggleSaved'])->name('saved.toggle');

    // Saved relatives — managed inline from the booking form (no
    // dedicated /account/relatives page per the spec).
    Route::patch('/account/relatives/{relative}', [AccountController::class, 'updateRelative'])
        ->name('account.relatives.update');
    Route::delete('/account/relatives/{relative}', [AccountController::class, 'destroyRelative'])
        ->name('account.relatives.destroy');
});

// Admin-managed static content pages (About, Privacy, Terms, Contact, FAQ).
// MUST stay LAST: this single-segment catch-all only matches slugs that no
// earlier route claimed, so reserved words (search/login/account/...) win.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('pages.show');
