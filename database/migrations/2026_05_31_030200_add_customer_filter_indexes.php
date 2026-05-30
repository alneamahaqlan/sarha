<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes powering the Kanban's new Customer-backed
 * filters (VIP only / repeat only / has prior complaint / new only).
 *
 * Each filter is `Booking::whereHas('customer', fn($q) => $q->where(
 *   <counter>, '>=', N))` — MySQL optimizer needs an index that
 * starts with clinic_id (we always scope to one clinic) to avoid
 * scanning the customers table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->index(['clinic_id', 'completed_bookings'], 'customers_clinic_completed_idx');
            $table->index(['clinic_id', 'total_complaints'], 'customers_clinic_complaints_idx');
            $table->index(['clinic_id', 'total_bookings'], 'customers_clinic_total_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_clinic_completed_idx');
            $table->dropIndex('customers_clinic_complaints_idx');
            $table->dropIndex('customers_clinic_total_idx');
        });
    }
};
