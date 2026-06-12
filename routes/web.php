<?php

use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Public\AccountController;
use App\Http\Controllers\Public\ArticleController;
use App\Http\Controllers\Public\ClinicController;
use App\Http\Controllers\Public\ClinicRegistrationController;
use App\Http\Controllers\Public\BeforeAfterController;
use App\Http\Controllers\Public\CartController;
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

// Cart add is OUTSIDE auth so a guest can add → OTP-verify → log in mid-flow.
Route::post('/cart/add', [CartController::class, 'add'])->middleware('throttle:12,1')->name('cart.add');
Route::post('/cart/add/verify', [CartController::class, 'addVerify'])->middleware('throttle:15,1')->name('cart.add.verify');

// Fire-and-forget click tracking for clinic action buttons (sendBeacon, CSRF-excluded).
Route::post('/track/click', [TrackingController::class, 'click'])
    ->middleware('throttle:120,1')
    ->name('track.click');
// Story view tracking (sendBeacon, CSRF-excluded).
Route::post('/track/story', [TrackingController::class, 'story'])
    ->middleware('throttle:120,1')
    ->name('track.story');
// Landing pages (صفحات الهبوط). Registered before /clinic and the /{slug}
// catch-all so /l/... always resolves here. Tracking beacons are CSRF-excluded
// (see bootstrap/app.php) and throttled.
Route::get('/l/{slug}', [\App\Http\Controllers\Public\LandingPageController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')->name('landing.show');
Route::post('/l/{slug}/book', [\App\Http\Controllers\Public\LandingPageController::class, 'book'])
    ->middleware('throttle:12,1')->name('landing.book');
Route::post('/l/track/visit', [\App\Http\Controllers\Public\LandingTrackingController::class, 'visit'])
    ->middleware('throttle:60,1')->name('landing.track.visit');
Route::post('/l/track/event', [\App\Http\Controllers\Public\LandingTrackingController::class, 'event'])
    ->middleware('throttle:120,1')->name('landing.track.event');
Route::post('/l/track/leave', [\App\Http\Controllers\Public\LandingTrackingController::class, 'leave'])
    ->middleware('throttle:60,1')->name('landing.track.leave');

Route::get('/clinic/{slug}', [ClinicController::class, 'show'])->name('clinic.show');
Route::get('/clinic/{slug}/book', [ClinicController::class, 'bookingForm'])->name('clinic.book.form');
Route::post('/clinic/{slug}/book', [ClinicController::class, 'book'])->middleware('throttle:12,1')->name('clinic.book');
Route::post('/clinic/{slug}/book/verify', [ClinicController::class, 'bookVerify'])->middleware('throttle:15,1')->name('clinic.book.verify');
Route::post('/clinic/{slug}/quote', [ClinicController::class, 'priceQuote'])->middleware('throttle:12,1')->name('clinic.quote');
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

// Follow a complex (Instagram-style). Public route on purpose: a guest who
// taps "Follow" is sent through the OTP login and the follow is applied on
// return (handled in OtpController via the pending_follow session key).
Route::post('/clinic/{clinic:slug}/follow', [AccountController::class, 'toggleFollow'])
    ->name('clinic.follow.toggle');

// Broadcast price-quote requests (not tied to a single clinic).
Route::get('/quotes', [QuoteController::class, 'board'])->name('quotes.board');
Route::get('/quotes/new', [QuoteController::class, 'requestForm'])->name('quotes.request');
Route::post('/quotes', [QuoteController::class, 'store'])->middleware('throttle:12,1')->name('quotes.store');
Route::post('/quotes/verify', [QuoteController::class, 'storeVerify'])->middleware('throttle:15,1')->name('quotes.verify');
Route::get('/quotes/{quoteRequest}', [QuoteController::class, 'show'])->whereNumber('quoteRequest')->name('quotes.show');

// Blog: index (all published articles) + standalone article page (SEO).
// Published articles of publicly-visible clinics only.
// Name is `blog.index` (not `articles.index`) to avoid colliding with the
// admin API resource route of the same name — route() would otherwise
// resolve to the API endpoint.
Route::get('/articles', [ArticleController::class, 'index'])->name('blog.index');
Route::get('/article/{slug}', [ArticleController::class, 'show'])->name('article.show');

// Public "List your complex" — creates a SalesLead for the admin pipeline.
Route::get('/register-clinic', [ClinicRegistrationController::class, 'show'])->name('clinic.register');
Route::post('/register-clinic', [ClinicRegistrationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('clinic.register.submit');

// Customer OTP auth
Route::middleware('guest:web')->group(function () {
    Route::get('/login', [OtpController::class, 'showLogin'])->name('login');
    Route::post('/login/send-otp', [OtpController::class, 'sendOtp'])->middleware('throttle:8,1')->name('login.otp');
    Route::post('/login/verify', [OtpController::class, 'verifyOtp'])->middleware('throttle:15,1')->name('login.verify');
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

    // Shopping cart — viewing + removing require a logged-in customer
    // (adding lives outside this group so guests can OTP in).
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    // Book-from-cart: stamps booked_at (conversion signal) then forwards to the real target.
    Route::get('/cart/{cartItem}/book', [CartController::class, 'book'])->name('cart.book');
    Route::delete('/cart/{cartItem}', [CartController::class, 'remove'])->name('cart.remove');

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
