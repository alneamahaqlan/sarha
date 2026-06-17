<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashback rewards — NOT money. Each row is a single-use voucher owned by
 * a person (keyed to the platform-wide identity by phone), locked to the
 * issuing clinic, redeemable against a specific offer (discount) or a
 * specific free service. Minted at attendance-confirmation (per the
 * clinic's rule) or granted manually by the clinic.
 *
 * Ownership: platform_customer_id is the canonical owner (survives
 * transfer by phone); customer_id is the per-clinic CRM context; phone is
 * a denormalized snapshot for fast lookup + guest holders with no User.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone')->index();

            // What the voucher grants.
            $table->enum('type', ['offer_discount', 'free_service']);
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            // Only for offer_discount; free_service is the implicit 100% case.
            $table->enum('discount_type', ['percent', 'amount'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();

            $table->enum('status', ['active', 'used', 'expired', 'void'])->default('active');
            $table->enum('source', ['attendance', 'manual']);

            // The attended booking that minted it (auto-grants) — also the
            // idempotency + reversal key when attendance is revoked.
            $table->foreignId('origin_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            // The booking it was finally redeemed against (if any).
            $table->foreignId('redeemed_booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            $table->string('code', 16)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();

            // Who granted it manually (Clinic owner or ClinicTeamMember) +
            // a name snapshot so credit survives member removal.
            $table->nullableMorphs('granted_by');
            $table->string('granted_by_name')->nullable();

            // Transfer provenance — set when received from another holder.
            $table->timestamp('transferred_at')->nullable();
            $table->string('transferred_from_phone')->nullable();

            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['platform_customer_id', 'status']);
            $table->index('origin_booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_vouchers');
    }
};
