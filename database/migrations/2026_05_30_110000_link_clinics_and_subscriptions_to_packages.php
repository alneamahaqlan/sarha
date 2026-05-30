<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wires the new packages catalogue into the two existing tables that
 * track who-owes-what:
 *
 *   clinics.subscription_package_id     → the package currently in
 *      force for the clinic. Nullable so a clinic that's never had
 *      a subscription stays valid until backfill seeds them. The old
 *      `subscription_type` string column is KEPT for backward compat
 *      with code paths that still read it; a seeded observer (later)
 *      syncs it from the package slug on update.
 *
 *   subscriptions.subscription_package_id → which tier was sold in
 *      this particular subscription row. Historical rows keep their
 *      package even if the global catalogue changes later.
 *
 *   subscriptions.billing_cycle / bonus_months → the new billing
 *      shape. Quarterly is the default cycle; annual lets the admin
 *      grant N bonus months (recorded so receipts / renewal math
 *      can reproduce the deal later).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Widen legacy `subscription_type` from ENUM('basic','premium')
        // to a plain VARCHAR so it can also carry 'free' (and any
        // future slug the admin invents) for backward compat.
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('subscription_type', 32)->nullable()->change();
        });

        Schema::table('clinics', function (Blueprint $table) {
            $table->foreignId('subscription_package_id')
                ->nullable()
                ->after('subscription_type')
                ->constrained('subscription_packages')
                ->nullOnDelete();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('subscription_package_id')
                ->nullable()
                ->after('clinic_id')
                ->constrained('subscription_packages')
                ->nullOnDelete();

            // quarterly = 3 months (default), annual = 12 months. We
            // keep the chosen cycle on the row so admin reports +
            // renewal flows don't need to back-derive it from dates.
            $table->string('billing_cycle', 16)->default('quarterly')->after('subscription_package_id');

            // For annual subs: how many free months the admin
            // generously included (e.g., "pay 10, get 12").
            // Recorded so the next renewal can repeat the offer
            // without admin re-keying it.
            $table->unsignedTinyInteger('bonus_months')->default(0)->after('billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['subscription_package_id']);
            $table->dropColumn(['subscription_package_id', 'billing_cycle', 'bonus_months']);
        });

        Schema::table('clinics', function (Blueprint $table) {
            $table->dropForeign(['subscription_package_id']);
            $table->dropColumn('subscription_package_id');
        });
    }
};
