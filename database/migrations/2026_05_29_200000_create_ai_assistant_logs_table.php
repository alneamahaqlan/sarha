<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Assistant — silent audit log of every interaction with the public
 * chat assistant ("سلمى"). Phase 1 only writes; Phase 2 reads from this
 * for stats + the searchable conversation viewer.
 *
 * Two satellite pivots capture WHICH clinics + categories surfaced in
 * each response, so we can compute "this clinic showed up in X chat
 * recommendations this month" without scanning JSON.
 *
 * Privacy: `query` and `reply` are stored verbatim so the admin can
 * review what the assistant said. Visitors (no auth) are tracked by an
 * opaque per-request UUID — never an IP or cookie — so retention is
 * survivable from a compliance standpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_logs', function (Blueprint $table) {
            $table->id();
            // Either user_id (an authenticated customer/admin/clinic) OR
            // visitor_id (an opaque uuid for anonymous chat). Never both.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_id', 64)->nullable();
            $table->string('guard', 16)->nullable(); // 'web' | 'admin' | 'clinic' | null

            $table->text('query');
            $table->text('reply')->nullable();
            // Mirrors the assistant service's `kind` discriminator:
            // matched / details / freeform / no_match / greeting / follow_up /
            // out_of_scope / rejected / emergency / inappropriate / empty.
            $table->string('kind', 32)->index();

            // Provider/model at the moment of the call — historical record
            // even if admin later switches providers.
            $table->string('provider', 32)->nullable();
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->string('locale', 5)->nullable();

            // Restriction outcomes. blocked_by_restriction_id points at
            // the rule that nuked the reply (if any). was_emergency is
            // true when the query tripped any emergency keyword (built-in
            // OR admin-added).
            $table->foreignId('blocked_by_restriction_id')->nullable();
            $table->boolean('was_emergency')->default(false)->index();
            $table->boolean('was_blocked')->default(false)->index();

            // Whether this log's reply triggered the silent
            // ClinicStat::bump for the surfaced clinics — set after the
            // bump runs so a partial failure leaves a debuggable marker.
            $table->boolean('stats_bumped')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['visitor_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('ai_assistant_log_clinics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_id')->constrained('ai_assistant_logs')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['log_id', 'clinic_id']);
            $table->index(['clinic_id', 'created_at']);
        });

        Schema::create('ai_assistant_log_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_id')->constrained('ai_assistant_logs')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['log_id', 'category_id']);
            $table->index(['category_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_log_categories');
        Schema::dropIfExists('ai_assistant_log_clinics');
        Schema::dropIfExists('ai_assistant_logs');
    }
};
