<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `crm_enabled` feature flag to subscription packages.
 *
 * Default TRUE on purpose: every existing package (free included) keeps
 * the operational CRM surface (customers, reminders, campaigns, bookings,
 * price-quotes, complaints, reports) it already had. Turning CRM into a
 * paid differentiator is then a single toggle per package in the admin UI
 * — no clinic loses access the day this lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->boolean('crm_enabled')->default(true)->after('allow_doctors_before_after');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->dropColumn('crm_enabled');
        });
    }
};
