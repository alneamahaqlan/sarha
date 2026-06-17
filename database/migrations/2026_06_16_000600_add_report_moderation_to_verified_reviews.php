<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spam/abuse reporting + admin moderation for verified reviews. ALL
 * additive — the review's text/ratings are never touched.
 *
 * Reporting (clinic) flags a review for admin review but DOES NOT hide it
 * — `is_visible` stays true until an admin confirms. Moderation (admin)
 * may set is_visible=false ONLY for spam/abuse, with a mandatory reason
 * and a full audit trail (who/when/why). A genuine negative is never a
 * valid reason to hide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verified_reviews', function (Blueprint $table) {
            // Clinic report (flag only — does not hide).
            $table->timestamp('reported_at')->nullable()->after('is_visible');
            $table->enum('report_reason', ['spam', 'abuse', 'fake', 'other'])->nullable()->after('reported_at');
            $table->string('report_note', 500)->nullable()->after('report_reason');
            $table->string('reported_by_name')->nullable()->after('report_note');

            // Admin moderation decision + audit.
            $table->timestamp('moderated_at')->nullable()->after('reported_by_name');
            $table->foreignId('moderated_by_admin_id')->nullable()->after('moderated_at')
                ->constrained('admins')->nullOnDelete();
            $table->enum('moderation_action', ['hidden', 'dismissed'])->nullable()->after('moderated_by_admin_id');
            $table->text('moderation_reason')->nullable()->after('moderation_action');

            // The pending-moderation queue: reported but not yet decided.
            $table->index(['reported_at', 'moderated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('verified_reviews', function (Blueprint $table) {
            $table->dropIndex(['reported_at', 'moderated_at']);
            $table->dropConstrainedForeignId('moderated_by_admin_id');
            $table->dropColumn([
                'reported_at', 'report_reason', 'report_note', 'reported_by_name',
                'moderated_at', 'moderation_action', 'moderation_reason',
            ]);
        });
    }
};
