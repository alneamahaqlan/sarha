<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer-scoped tags, FK'd to the unified Customer entity from
 * phase 1. Replaces booking_customer_tags (keyed on phone, dropped
 * in the next migration after backfill).
 *
 * "yEverywhere a customer appears" labels — e.g. "يَفضّل العربية",
 * "حساسية من بنج". Applied at the Customer level so they show on
 * every booking card for that customer automatically.
 *
 * Booking-scoped tags (booking_tags) are unaffected — they remain
 * pinned to a single visit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('color', 16);
            $table->nullableMorphs('created_by');
            $table->timestamps();

            $table->unique(['customer_id', 'label'], 'customer_tags_customer_label_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tags');
    }
};
