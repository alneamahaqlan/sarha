<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the new unified-notification fields to the existing
 * platform_notifications table:
 *
 *  - event_type      : NotificationEvent enum value (booking_created,
 *                      ai_emergency, …). Replaces the legacy free-form
 *                      `type` for new dispatches — `type` stays for
 *                      backward compat until all call sites migrate.
 *  - action_buttons  : optional JSON array of {label, url, style}
 *                      so the React bell can render inline actions
 *                      ("Confirm" / "Reply") without re-fetching.
 *  - group_key       : reserved for Phase D grouping ("5 new bookings"
 *                      collapses on the same group_key). Nullable,
 *                      unused in Phase A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_notifications', function (Blueprint $table) {
            $table->string('event_type', 64)->nullable()->after('type')->index();
            $table->json('action_buttons')->nullable()->after('data');
            $table->string('group_key', 80)->nullable()->after('action_buttons')->index();
        });
    }

    public function down(): void
    {
        Schema::table('platform_notifications', function (Blueprint $table) {
            $table->dropIndex(['event_type']);
            $table->dropIndex(['group_key']);
            $table->dropColumn(['event_type', 'action_buttons', 'group_key']);
        });
    }
};
