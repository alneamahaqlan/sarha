<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags scoped to a customer (identified by phone) within a clinic.
 * Auto-applied to every booking sharing that (clinic_id, phone) pair —
 * e.g. "يَفضّل العربية", "تابع مع د. أحمد".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_customer_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('customer_phone', 32);
            $table->string('label', 60);
            $table->string('color', 16);
            $table->nullableMorphs('created_by');
            $table->timestamps();

            $table->unique(['clinic_id', 'customer_phone', 'label'], 'cust_tags_clinic_phone_label_uq');
            $table->index(['clinic_id', 'customer_phone'], 'cust_tags_clinic_phone_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_customer_tags');
    }
};
