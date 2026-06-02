<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pricing-detail fields on clinic services:
 *  - price_from:     when true the price is a "starting from" minimum, shown
 *                    as «ابتداءً من X» instead of a fixed price.
 *  - price_includes: optional note on what the price covers (e.g. الكشف الأولي).
 *  - price_excludes: optional note on what it does not cover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('price_from')->default(false)->after('price');
            $table->text('price_includes')->nullable()->after('price_from');
            $table->text('price_excludes')->nullable()->after('price_includes');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['price_from', 'price_includes', 'price_excludes']);
        });
    }
};
