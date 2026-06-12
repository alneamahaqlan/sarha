<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sales pipeline upgrade:
     *   - acquisition source (where the lead came from)
     *   - loss reason + notes (analysable churn at the top of funnel)
     *   - followup_notified_at (idempotency stamp for the overdue-followup
     *     reminder command; cleared whenever next_follow_up_at changes)
     *   - sales_lead_activities: append-only timeline (call/whatsapp/email/
     *     note/meeting/status_change) per lead.
     */
    public function up(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            $table->string('source', 32)->nullable()->after('status');
            $table->string('lost_reason', 32)->nullable()->after('source');
            $table->text('lost_notes')->nullable()->after('lost_reason');
            $table->timestamp('followup_notified_at')->nullable()->after('last_contact_at');
        });

        Schema::create('sales_lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            // Actor snapshot — kept even if the admin row is later removed.
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('admin_name')->nullable();
            // note | call | whatsapp | email | meeting | status_change
            $table->string('type', 24);
            $table->text('body')->nullable();
            // For status_change rows: {"from": "...", "to": "..."}.
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['sales_lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_lead_activities');

        Schema::table('sales_leads', function (Blueprint $table) {
            $table->dropColumn(['source', 'lost_reason', 'lost_notes', 'followup_notified_at']);
        });
    }
};
