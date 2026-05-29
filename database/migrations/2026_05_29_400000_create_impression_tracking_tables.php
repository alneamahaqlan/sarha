<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-source impression tracking. Replaces the single-counter
 * `clinic_stats.search_appearances` column with a per-source daily
 * breakdown across 6 surfaces: search, filter, home, similar_services,
 * ai, compare.
 *
 * Two tables instead of one because the spec requires both clinic-level
 * and service-level analytics — and the cascade rule (a service
 * impression also bumps its clinic) means clinic_impressions ends up
 * with strictly more entries than service_impressions.
 *
 * Daily aggregation: (entity_id, date, source) is unique; we
 * upsert+increment instead of inserting one row per event. Hot path is
 * cheap, and stats queries stay fast.
 *
 * Legacy `clinic_stats.search_appearances` is kept for backward compat
 * (older queries still read it during the migration window) but the new
 * UI computes everything from these tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // search | filter | home | similar_services | ai | compare
            $table->string('source', 32);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['clinic_id', 'date', 'source'], 'clinic_impressions_unique');
            $table->index(['date', 'source'], 'clinic_impressions_aggregate_idx');
        });

        Schema::create('service_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            // Denormalized clinic_id so we can join/group without
            // touching the services table on every read.
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('source', 32);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['service_id', 'date', 'source'], 'service_impressions_unique');
            $table->index(['clinic_id', 'date', 'source'], 'service_impressions_clinic_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_impressions');
        Schema::dropIfExists('clinic_impressions');
    }
};
