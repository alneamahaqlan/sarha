<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unique-viewer tracking for clinics ("المشاهدات").
 *
 * Where clinic_impressions counts EVERY appearance (a visitor who sees a
 * clinic 10 times adds 10), this table counts DISTINCT visitors: one row
 * per (clinic, visitor, 3-hour bucket). The same visitor seeing the same
 * clinic again within the same 3-hour window is ignored (insertOrIgnore on
 * the unique key), so 10 views by one person in the window = 1 row.
 *
 *   - visitor_hash : sha256 of the logged-in user id, or the session id for
 *                    guests — no raw PII stored.
 *   - bucket       : floor(unixTimestamp / 10800) — the 3-hour window index
 *                    that implements the "unique every 3 hours" rule.
 *   - date         : the calendar day of the view (app timezone), kept for
 *                    cheap per-day grouping in the stats trend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('bucket');
            $table->string('visitor_hash', 64);
            $table->timestamps();

            // One unique viewer per clinic per 3-hour window.
            $table->unique(['clinic_id', 'bucket', 'visitor_hash'], 'clinic_views_unique');
            // Per-clinic range scans (clinic stats page).
            $table->index(['clinic_id', 'date'], 'clinic_views_clinic_date_idx');
            // Platform-wide range scans (admin analytics).
            $table->index('date', 'clinic_views_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_views');
    }
};
