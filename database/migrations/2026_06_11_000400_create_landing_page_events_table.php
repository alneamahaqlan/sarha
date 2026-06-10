<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only behavioral events for a landing-page visit (scroll milestones,
 * dwell ticks, clicks, whatsapp/call, gallery/video, form start/submit,
 * registration, booking, conversion). `payload` only ever holds allow-listed
 * keys — never medical service names or other health context. The
 * landing_page_id is denormalized so the daily rollup never has to join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_page_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->nullable();

            $table->index(['landing_page_id', 'type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_events');
    }
};
