<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two complementary stores that power the super-admin "comprehensive
 * user profile" screen:
 *
 *  - user_activity_events: meaningful events the existing domain
 *    tables (bookings/complaints/AI logs) don't already cover —
 *    searches, page views, action-button clicks, login/logout,
 *    account edits. Lives outside the per-domain tables so the
 *    timeline can union with them without duplicating rows already
 *    in bookings/complaints/etc.
 *
 *  - user_visit_sessions: real visit-level history (one row per
 *    visit), so totals (count, sum duration, last login, avg) are
 *    derivable from a single grouped query. Laravel's `sessions`
 *    table only keeps the LATEST session per user_id — it would
 *    silently overwrite the data we need to compute those totals.
 *
 * Both tables are scoped per user_id so a profile load is a small
 * scan + a fast (user_id, occurred_at) range query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // `type` mirrors the timeline icon set on the React side.
            // Kept as a string (not enum) so admins / future code can
            // add new event kinds without a migration. The known set
            // for v1: login, logout, search, filter, clinic_view,
            // action_click, account_edit, compare, otp_verified.
            $table->string('type', 40);

            // Short human-readable hook the React side renders verbatim
            // when no i18n key matches the `type`. Keeps the timeline
            // resilient if a new event type is added before the locale
            // files learn its label.
            $table->string('title')->nullable();

            // Free-form context the timeline + filter use:
            //   search:        { q, city_id, category_id, results_count }
            //   filter:        { city_id, category_id }
            //   clinic_view:   { clinic_id, slug }
            //   action_click:  { clinic_id, button: whatsapp|call|directions|book }
            //   account_edit:  { changes: { field: { from, to } } }
            //   compare:       { clinic_ids: [..] }
            $table->json('details')->nullable();

            // Optional pointer to a clinic the event touches — kept
            // denormalized so the timeline filter "show events touching
            // clinic X" is an indexed where clause.
            $table->foreignId('related_clinic_id')->nullable()
                ->constrained('clinics')->nullOnDelete();

            // Optional public URL the event resolves to (for "click to
            // navigate" affordance on each timeline row).
            $table->string('related_url')->nullable();

            // Visit-level context if known. Helps "session expand"
            // future UX without joining; null when middleware can't
            // resolve a session id.
            $table->foreignId('visit_session_id')->nullable();

            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 255)->nullable();

            // `occurred_at` is distinct from `created_at` because for
            // back-filled / seeded events we want to control the
            // chronology independent of the row insertion time.
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['user_id', 'type']);
            $table->index(['related_clinic_id', 'occurred_at']);
        });

        Schema::create('user_visit_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            // The middleware extends this on every authenticated
            // request; the "time on platform" sum stops here, not at
            // some hypothetical tab-still-open moment — spec line:
            // "لا يُحسَب الوقت لـ tabs مفتوحة بدون نشاط حقيقي".
            $table->timestamp('last_activity_at');
            // Set when the visit is officially closed (explicit logout
            // OR background "close idle visits" job). Until then the
            // session counts as the user's current visit.
            $table->timestamp('ended_at')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_events');
        Schema::dropIfExists('user_visit_sessions');
    }
};
