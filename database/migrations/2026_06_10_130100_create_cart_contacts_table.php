<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Logs each time a clinic reaches out to a customer about their
     * abandoned cart. Keyed by (clinic_id, user_id) because an "abandoned
     * cart" is logically one user's unbooked items at one clinic. Drives
     * the "contacted in the last 3 days?" flag on the admin/clinic views
     * without the fuzzy users↔customers phone match.
     */
    public function up(): void
    {
        Schema::create('cart_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 16)->default('manual'); // whatsapp | call | manual
            $table->text('note')->nullable();
            // Actor snapshot (Clinic owner or ClinicTeamMember), mirrors Booking/CustomerNote.
            $table->string('created_by_type')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'user_id', 'created_at'], 'cart_contacts_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_contacts');
    }
};
