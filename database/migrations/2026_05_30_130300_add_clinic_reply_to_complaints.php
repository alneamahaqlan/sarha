<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Complaints today track only admin-side handling (admin_notes,
        // resolution, assigned_admin_id). The clinic's reply to a
        // customer-sourced complaint is a brand-new mechanism — kept
        // as flat columns rather than a separate replies table because
        // spec specifies one reply per complaint.
        Schema::table('complaints', function (Blueprint $table) {
            $table->text('clinic_reply_text')->nullable()->after('resolution');
            $table->foreignId('clinic_replied_by_member_id')
                ->nullable()
                ->after('clinic_reply_text')
                ->constrained('clinic_team_members')
                ->nullOnDelete();
            $table->string('clinic_replied_by_name_snapshot')->nullable()->after('clinic_replied_by_member_id');
            $table->string('clinic_replied_by_role_snapshot', 16)->nullable()->after('clinic_replied_by_name_snapshot');
            $table->timestamp('clinic_replied_at')->nullable()->after('clinic_replied_by_role_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinic_replied_by_member_id');
            $table->dropColumn([
                'clinic_reply_text',
                'clinic_replied_by_name_snapshot',
                'clinic_replied_by_role_snapshot',
                'clinic_replied_at',
            ]);
        });
    }
};
