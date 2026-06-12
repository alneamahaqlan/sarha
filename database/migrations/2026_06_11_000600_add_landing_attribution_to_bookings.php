<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landing-page attribution for bookings. When a booking originates from a
 * landing page it carries the landing_page_id + the captured UTM tags, so the
 * conversion shows up in BOTH the landing-page analytics and (via the existing
 * ClinicStat::bump path) the linked clinic's stats. `source` stays 'website'
 * and `acquisition_source` is set to 'landing' — no enum migration needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('landing_page_id')->nullable()->after('clinic_id')
                ->constrained()->nullOnDelete();
            $table->string('utm_source')->nullable()->after('acquisition_source');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('utm_content')->nullable()->after('utm_campaign');
            $table->string('utm_term')->nullable()->after('utm_content');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('landing_page_id');
            $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']);
        });
    }
};
