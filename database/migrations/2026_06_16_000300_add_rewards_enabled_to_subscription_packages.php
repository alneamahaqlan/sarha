<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `rewards_enabled` feature flag to subscription packages.
 *
 * Default FALSE on purpose: cashback rewards are a new, monetizable
 * retention feature — the admin opts a package IN per the rollout, unlike
 * crm_enabled which defaulted ON to preserve existing access. Gated via
 * FeatureGate::hasRewardsAccess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->boolean('rewards_enabled')->default(false)->after('crm_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->dropColumn('rewards_enabled');
        });
    }
};
