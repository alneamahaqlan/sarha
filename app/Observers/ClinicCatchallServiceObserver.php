<?php

namespace App\Observers;

use App\Models\Clinic;

/**
 * Ensures every clinic owns its "خدمات أخرى" (other services) catch-all
 * service. Fires on create so a freshly-registered clinic immediately offers
 * the fallback option in its booking forms. Existing clinics were backfilled
 * by the add_is_catchall_to_services migration.
 */
class ClinicCatchallServiceObserver
{
    public function created(Clinic $clinic): void
    {
        $clinic->catchallService();
    }
}
