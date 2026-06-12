<?php

use App\Models\Clinic;
use App\Models\Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Introduce the per-clinic "خدمات أخرى" (other services) catch-all service.
 *
 * It is a real Service row (so bookings.service_id can point at it and it
 * counts in every per-clinic service report exactly like a normal service),
 * flagged with is_catchall so we can:
 *   - hide it from the public services showcase grid,
 *   - lock it from edit/delete in the clinic admin,
 *   - exclude it from the plan's services limit.
 *
 * One row is backfilled for every existing clinic; new clinics get theirs
 * via ClinicCatchallServiceObserver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_catchall')->default(false)->after('approval_status');
        });

        // Backfill: every clinic gets exactly one catch-all service. Uses the
        // model helper so the creation rule lives in one place.
        Clinic::query()->orderBy('id')->chunkById(200, function ($clinics) {
            foreach ($clinics as $clinic) {
                $clinic->catchallService();
            }
        });
    }

    public function down(): void
    {
        Service::query()->where('is_catchall', true)->forceDelete();

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('is_catchall');
        });
    }
};
