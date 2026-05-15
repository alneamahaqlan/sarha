<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Clinic;
use App\Models\SalesLead;
use App\Models\Subscription;
use App\Models\User;
use App\Observers\AuditObserver;
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
    }
}
