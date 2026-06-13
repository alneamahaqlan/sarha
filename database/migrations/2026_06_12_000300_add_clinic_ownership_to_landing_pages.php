<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landing pages can now be OWNED by a complex, not only built by a super-admin.
 *
 * A clinic-owned page (`owner_clinic_id` set) is vetted once before its first
 * public appearance: it travels draft → pending → approved|rejected via the
 * admin "Access Center". Public visibility requires `approval_status=approved`
 * (see LandingPage::scopePubliclyLive). Admin-built pages keep `owner_clinic_id`
 * null and default to `approved`, so their existing behaviour is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            // Owning complex — null means a platform/admin-built page.
            $table->foreignId('owner_clinic_id')->nullable()->after('created_by')
                ->constrained('clinics')->nullOnDelete();
            $table->index('owner_clinic_id');

            // One-time approval gate. Existing rows default to 'approved' so
            // admin-built pages stay live without a backfill.
            $table->enum('approval_status', ['draft', 'pending', 'approved', 'rejected'])
                ->default('approved')->after('owner_clinic_id')->index();
            $table->text('approval_reason')->nullable()->after('approval_status');
            $table->timestamp('submitted_at')->nullable()->after('approval_reason');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->foreignId('approval_reviewed_by')->nullable()->after('reviewed_at')
                ->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_reviewed_by');
            $table->dropConstrainedForeignId('owner_clinic_id');
            $table->dropColumn(['approval_status', 'approval_reason', 'submitted_at', 'reviewed_at']);
        });
    }
};
