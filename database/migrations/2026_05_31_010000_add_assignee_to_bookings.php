<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic assignee on bookings so the Kanban "بمتابعة: X" picker
 * can target either the Clinic owner or a ClinicTeamMember without
 * splitting into two nullable FKs. Mirrors the actor shape already
 * used by ClinicActivityLog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->nullableMorphs('assignee');
            $table->index(['clinic_id', 'status', 'appointment_at'], 'bookings_clinic_status_appt_idx');
            $table->index(['clinic_id', 'customer_phone'], 'bookings_clinic_phone_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_clinic_phone_idx');
            $table->dropIndex('bookings_clinic_status_appt_idx');
            $table->dropMorphs('assignee');
        });
    }
};
