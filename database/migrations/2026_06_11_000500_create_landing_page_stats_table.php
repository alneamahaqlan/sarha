<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily rollup counters per landing page — mirrors `clinic_stats`. Bumped live
 * on ingestion and self-healed nightly from visits/events. Traffic-source
 * breakdown is computed on demand by grouping visits on utm_source, so no
 * per-source columns live here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedInteger('whatsapp_clicks')->default(0);
            $table->unsignedInteger('bookings_count')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->unsignedInteger('visits')->default(0);
            $table->unsignedInteger('bounces')->default(0);
            $table->unsignedBigInteger('sum_duration_seconds')->default(0);

            $table->timestamps();

            $table->unique(['landing_page_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_stats');
    }
};
