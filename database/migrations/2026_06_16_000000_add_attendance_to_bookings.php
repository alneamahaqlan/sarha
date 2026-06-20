<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance is an INDEPENDENT signal: did the patient physically
     * show up for their appointment? It is deliberately separate from
     * the `status` enum (completed / no_show) and from the Kanban — a
     * booking can sit in `appointment_set` while `attended_at` is
     * stamped, and the board is untouched.
     *
     * Mirrors the created_by pattern: a nullable polymorph (Clinic owner
     * or ClinicTeamMember) plus an `attended_by_name` snapshot so the
     * credit survives even if the team member row is later removed.
     *
     * The stamp drives the customer-lifecycle loop (cashback on
     * attendance, verified reviews) which subscribe to the
     * BookingAttendanceConfirmed domain event — never the status column.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('attended_at')->nullable()->after('appointment_at');
            $table->nullableMorphs('attended_by');
            $table->string('attended_by_name')->nullable()->after('attended_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('attended_at');
            $table->dropMorphs('attended_by');
            $table->dropColumn('attended_by_name');
        });
    }
};
