<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Services a customer is INTERESTED in but has not (yet) purchased —
     * a per-customer wish/intent list the clinic fills in. Powers the
     * "most-interested services" + "who is interested in service X"
     * reports, distinct from the booking_services purchase data.
     *
     * Unique on (customer_id, service_id): a customer is interested in a
     * service at most once. clinic_id denormalised for report scoping.
     */
    public function up(): void
    {
        Schema::create('customer_interested_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'service_id'], 'cis_customer_service_unique');
            $table->index(['clinic_id', 'service_id'], 'cis_clinic_service_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_interested_services');
    }
};
