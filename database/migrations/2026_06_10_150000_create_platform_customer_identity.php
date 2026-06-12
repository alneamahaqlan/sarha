<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotes "customer" from a per-clinic row to a single platform-wide
 * identity keyed by the normalized phone number.
 *
 *   platform_customers     — one row per human, identified by phone.
 *                            Holds the canonical (self-registered) name
 *                            and whether the phone is OTP-verified.
 *   customer_name_aliases  — every name the entity has ever been given,
 *                            tagged with its source (self / clinic / import)
 *                            and the clinic that supplied it. Drives the
 *                            "first name wins, the rest are alternatives"
 *                            display rule.
 *   customers.platform_customer_id — links each existing per-clinic CRM
 *                            row up to its shared identity. The per-clinic
 *                            row keeps all its CRM state (notes, tags,
 *                            counters); only identity is unified above it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_customers', function (Blueprint $table) {
            $table->id();
            // Normalized E.164 (+9665XXXXXXXX) — the one true identifier.
            $table->string('phone', 20)->unique();
            // Set when the human owns a self-registered platform account.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // The adopted display name: the self-registered name once the
            // customer signs up, otherwise the earliest clinic-supplied name.
            $table->string('canonical_name', 120)->nullable();
            // True only once the phone is proven via OTP (self sign-up).
            // Clinic-entered numbers stay "claimed" (false) until verified.
            $table->boolean('is_phone_verified')->default(false);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_name_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_customer_id')
                ->constrained('platform_customers')->cascadeOnDelete();
            // null = the self-registered / platform name (no owning clinic).
            $table->foreignId('clinic_id')->nullable()
                ->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 120);
            $table->enum('source', ['self', 'clinic', 'import'])->default('clinic');
            $table->timestamps();

            // Re-adding an identical name from the same source is a no-op.
            $table->unique(['platform_customer_id', 'clinic_id', 'name'], 'cust_alias_uq');
            // "first name wins" ordering for a given (entity, clinic).
            $table->index(['platform_customer_id', 'clinic_id', 'created_at'], 'cust_alias_order_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('platform_customer_id')->nullable()->after('id')
                ->constrained('platform_customers')->nullOnDelete();
            $table->index('platform_customer_id', 'customers_platform_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_platform_idx');
            $table->dropConstrainedForeignId('platform_customer_id');
        });
        Schema::dropIfExists('customer_name_aliases');
        Schema::dropIfExists('platform_customers');
    }
};
