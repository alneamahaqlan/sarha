<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags scoped to a single booking — e.g. "يَحتاج تَخدير", "تَحويل من
 * د. سارة". Independent of customer-level tags so the same customer
 * can carry distinct booking-specific notes per visit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('color', 16);
            $table->nullableMorphs('created_by');
            $table->timestamps();

            $table->unique(['booking_id', 'label'], 'booking_tags_booking_label_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_tags');
    }
};
