<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user shopping cart. Mirrors `saved_items`: a polymorphic row per
     * cart entry pointing at a Service / Offer / Package. Because guests must
     * OTP-verify before anything is added, every cart row is always bound to a
     * real user — no guest/session cart is needed.
     *
     * `clinic_id` is denormalised on add so the cart page can group by clinic
     * and the per-clinic gate can be checked without loading each polymorphic
     * target. `booked_at` marks an item the customer has already booked.
     */
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('cartable'); // cartable_type + cartable_id (+ index)
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'cartable_type', 'cartable_id'], 'cart_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
