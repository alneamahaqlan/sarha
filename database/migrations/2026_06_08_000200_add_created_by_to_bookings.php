<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track who created a booking — the clinic team member (or clinic
     * owner) who entered it manually. Mirrors the customer_notes pattern:
     * a nullable polymorph plus a `created_by_name` snapshot so the
     * credit survives even if the team member row is later removed.
     *
     * Customer-originated bookings (source = website) leave created_by
     * null — the patient booked themselves. The fixed creation date is
     * the existing `created_at` timestamp (immutable after insert).
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->nullableMorphs('created_by');
            $table->string('created_by_name')->nullable()->after('created_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropMorphs('created_by');
            $table->dropColumn('created_by_name');
        });
    }
};
