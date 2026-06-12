<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acquisition source for bookings — the marketing channel the
     * patient came from (instagram, whatsapp, referral, walk_in, …).
     *
     * This is distinct from the existing `source` column, which records
     * the technical origin of the row (website = customer self-booked,
     * clinic = entered manually by the team). `acquisition_source` is
     * filled by the team when creating/editing a booking and powers the
     * Kanban filter + the "bookings by source" distribution report.
     * Defaults to 'other' when the team leaves it blank.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('acquisition_source', 32)->default('other')->after('source');
            $table->index(['clinic_id', 'acquisition_source'], 'bookings_clinic_acq_source_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_clinic_acq_source_idx');
            $table->dropColumn('acquisition_source');
        });
    }
};
