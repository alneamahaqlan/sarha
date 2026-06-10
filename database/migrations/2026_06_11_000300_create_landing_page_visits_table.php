<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per anonymous visitor session on a landing page. Captures UTM +
 * referrer on first hit, accumulates scroll depth / duration / bounce, and is
 * flipped to `converted` (with the booking) when the visitor submits. The
 * `user_id` is backfilled if the visitor later signs in. NEVER stores a raw
 * IP — only a salted hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();

            $table->string('visitor_id', 40)->index();  // anonymous cookie uuid (sarha_lv), 1yr
            $table->string('session_id', 40)->index();  // new session after 30-min idle
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Attribution.
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('referrer')->nullable();
            $table->string('landing_url')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device')->nullable();       // mobile | tablet | desktop
            $table->string('ip_hash', 64)->nullable();  // sha256, never raw

            // Engagement.
            $table->timestamp('started_at')->index();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedTinyInteger('scroll_depth')->default(0);  // 0-100 max reached
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->boolean('is_bounce')->default(true);

            // Conversion.
            $table->boolean('converted')->default(false)->index();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['landing_page_id', 'started_at']);
            $table->index(['landing_page_id', 'converted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_visits');
    }
};
