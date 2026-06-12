<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Line items for the services a customer actually TAKES on a booking
     * (a Kanban card). A card can hold MANY services, each with its own
     * net price — so the clinic/staff can apply a per-customer discount
     * on top of the catalog price.
     *
     *   list_price = snapshot of services.price at add time (for reporting
     *                on discount given; nullable since services may have
     *                no price).
     *   net_price  = the final price the staff typed (required). The card's
     *                value = SUM(net_price); a stage's value = SUM of its
     *                cards' values.
     *
     * clinic_id is denormalised so the service reports (best-selling /
     * buyers) aggregate without joining through bookings.
     *
     * Existing single-service bookings are backfilled below so historical
     * "إجمالي قيمة الخدمات" stays intact.
     */
    public function up(): void
    {
        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('list_price', 10, 2)->nullable();
            $table->decimal('net_price', 10, 2);
            $table->timestamps();

            $table->index(['booking_id'], 'bksvc_booking_idx');
            $table->index(['clinic_id', 'service_id'], 'bksvc_clinic_service_idx');
        });

        // Backfill: one line item per existing booking that has a service,
        // copying the catalog price into both list_price and net_price.
        DB::statement("
            INSERT INTO booking_services (clinic_id, booking_id, service_id, list_price, net_price, created_at, updated_at)
            SELECT b.clinic_id, b.id, b.service_id, s.price, s.price, NOW(), NOW()
            FROM bookings b
            JOIN services s ON s.id = b.service_id
            WHERE b.service_id IS NOT NULL AND b.deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_services');
    }
};
