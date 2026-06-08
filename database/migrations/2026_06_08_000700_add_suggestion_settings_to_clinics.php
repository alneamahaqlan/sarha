<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-clinic overrides for the Kanban card suggestions (the smart
     * next-step nudges: "تواصل أولي", "تأكيد عاجل", …). Each suggestion
     * can be switched off and the three time/count-based ones retuned.
     *
     * Shape (null/absent keys fall back to Clinic::SUGGESTION_DEFAULTS):
     *   {
     *     "confirm_urgent": {"enabled": true, "hours": 24},
     *     "first_contact":  {"enabled": true},
     *     "retry_call":     {"enabled": true, "hours": 2},
     *     "reminder_soon":  {"enabled": true, "hours": 48},
     *     "cancel_risk":    {"enabled": true, "count": 2}
     *   }
     *
     * The underlying booking statuses + auto-tags are untouched — this
     * only governs which suggestions surface and at what thresholds.
     */
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->json('suggestion_settings')->nullable()->after('booking_stage_labels');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn('suggestion_settings');
        });
    }
};
