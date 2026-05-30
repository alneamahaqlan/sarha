<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror of what we did for `clinics.subscription_type` — widen the
 * legacy ENUM('basic','premium') so it can also carry custom package
 * slugs the admin invents from the packages UI (`free`, `enterprise`,
 * whatever).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('type', 32)->change();
        });
    }

    public function down(): void
    {
        // intentionally no-op — going back to ENUM would lose data
        // for any rows carrying a non-{basic,premium} slug.
    }
};
