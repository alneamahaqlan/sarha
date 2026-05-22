<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Article;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Complaint;
use App\Models\PriceQuoteRequest;
use App\Models\SalesLead;
use App\Models\Subscription;
use App\Models\User;
use App\Observers\ArticleObserver;
use App\Observers\AuditObserver;
use App\Observers\BookingObserver;
use App\Observers\ComplaintObserver;
use App\Observers\PriceQuoteRequestObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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

        // Article publishing limit enforcement
        Article::observe(ArticleObserver::class);
    }
}
