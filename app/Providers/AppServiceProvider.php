<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Article;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Complaint;
use App\Models\PriceQuoteReply;
use App\Models\PriceQuoteRequest;
use App\Models\SalesLead;
use App\Models\Subscription;
use App\Models\User;
use App\Observers\ArticleObserver;
use App\Observers\AuditObserver;
use App\Observers\BookingCustomerLinkObserver;
use App\Observers\BookingObserver;
use App\Observers\ClinicActivityObserver;
use App\Observers\ComplaintCustomerLinkObserver;
use App\Observers\ComplaintObserver;
use App\Observers\PriceQuoteCustomerLinkObserver;
use App\Observers\PriceQuoteReplyObserver;
use App\Observers\PriceQuoteRequestObserver;
use App\View\Composers\LayoutComposer;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // CustomerInsightService caches per-customer derived signals
        // for the duration of a single request — make it a singleton
        // so the Kanban resource + the side-panel resource share the
        // same cache.
        $this->app->singleton(\App\Services\CustomerInsightService::class);

        // OTP delivery — WhatsApp provider + sender-rotation strategy are
        // bound to interfaces so either can be swapped without touching the
        // channels or the dispatcher (Dependency Inversion).
        $this->app->bind(
            \App\Contracts\Messaging\WhatsAppGateway::class,
            \App\Services\Messaging\Gateways\WappiGateway::class,
        );
        $this->app->bind(
            \App\Contracts\Messaging\WhatsAppSenderSelector::class,
            \App\Services\Messaging\Selectors\LeastRecentlyUsedSenderSelector::class,
        );

        // Tracking context for the current request. Defaults to disabled
        // (renders nothing); ResolveTrackingContext middleware replaces it
        // with the resolved context on public clinic pages.
        $this->app->singleton(
            \App\Services\Tracking\TrackingContext::class,
            fn () => \App\Services\Tracking\TrackingContext::disabled(),
        );
    }

    public function boot(): void
    {
        // Windows + Laragon needs OPENSSL_CONF set explicitly for EC
        // curve key operations (Web Push VAPID signing, OAuth, etc.).
        // We probe the bundled openssl.cnf and export it ourselves so
        // queue workers and CLI commands inherit a working OpenSSL
        // config without the user having to set a system env var.
        if (PHP_OS_FAMILY === 'Windows' && ! getenv('OPENSSL_CONF')) {
            $candidate = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras'
                . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
            if (is_file($candidate)) {
                putenv("OPENSSL_CONF={$candidate}");
            }
        }

        // Custom Tailwind pagination view used across the public Blade lists.
        Paginator::defaultView('vendor.pagination.saerha');

        // Public header/footer are admin-driven (navigation links + footer
        // contact/social settings) — a single composer feeds both partials.
        View::composer(
            ['layouts.partials.header', 'layouts.partials.footer'],
            LayoutComposer::class,
        );

        $auditable = [
            Clinic::class,
            Subscription::class,
            SalesLead::class,
            Admin::class,
            User::class,
        ];

        foreach ($auditable as $model) {
            $model::observe(AuditObserver::class);
        }

        // Notification triggers
        Booking::observe(BookingObserver::class);
        Complaint::observe(ComplaintObserver::class);
        PriceQuoteRequest::observe(PriceQuoteRequestObserver::class);
        PriceQuoteReply::observe(PriceQuoteReplyObserver::class);

        // Customer entity link/upkeep — runs alongside the
        // notification observers above. Each one resolves the owning
        // Customer (creating it on first sight) and keeps the
        // denormalized counters in sync.
        Booking::observe(BookingCustomerLinkObserver::class);
        Complaint::observe(ComplaintCustomerLinkObserver::class);
        PriceQuoteRequest::observe(PriceQuoteCustomerLinkObserver::class);

        // Article publishing limit enforcement
        Article::observe(ArticleObserver::class);

        // Clinic-team activity log — auto-records create/update/delete
        // for catalog models. Only fires when there's an acting clinic
        // user (skipped during seeders / artisan jobs). Each entry
        // includes the actor (owner or team member) so the clinic-owner
        // can answer "who edited this?" from one place.
        $clinicActivityModels = [
            \App\Models\Service::class,
            \App\Models\Doctor::class,
            \App\Models\Package::class, // clinic-side service bundles
            \App\Models\Offer::class,
            \App\Models\BeforeAfterPhoto::class,
            \App\Models\SubClinic::class,
            Article::class,
        ];
        foreach ($clinicActivityModels as $model) {
            if (class_exists($model)) {
                $model::observe(ClinicActivityObserver::class);
            }
        }
    }
}
