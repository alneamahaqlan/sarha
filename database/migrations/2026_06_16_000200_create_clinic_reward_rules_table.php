<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The clinic's single auto-grant rule (one row per clinic). When enabled,
 * every attendance-confirmed booking mints one voucher of the configured
 * shape. Disabled / absent = no automatic rewards (manual grants still
 * work). Kept as a dedicated 1:1 table rather than columns on `clinics`
 * to hold the offer/service FKs cleanly and leave room to grow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_reward_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);

            $table->enum('type', ['offer_discount', 'free_service'])->nullable();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('discount_type', ['percent', 'amount'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();

            // Days until a minted voucher expires; null = never expires.
            $table->unsignedInteger('validity_days')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_reward_rules');
    }
};
