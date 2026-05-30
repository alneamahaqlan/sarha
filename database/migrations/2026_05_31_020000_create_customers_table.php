<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified per-clinic customer entity. Each (clinic_id, phone) pair
 * is one row. Phone is always stored in E.164 form (+9665XXXXXXXX)
 * via App\Support\PhoneNormalizer.
 *
 * Counters (total_bookings, completed_bookings, total_complaints,
 * total_quote_requests) are denormalized so the Kanban/CRM/profile
 * surfaces never have to recount. Updates land via the three
 * Customer-link observers.
 *
 * user_id is a nullable link to the platform User row that owns the
 * same phone — populated by CustomerLinker at create time when the
 * lookup succeeds. Stays nullable for walk-in / phone-booking flows
 * where no User exists yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('name', 120);
            $table->string('email', 191)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->enum('last_interaction_type', ['booking', 'complaint', 'quote_request'])->nullable();

            $table->unsignedInteger('total_bookings')->default(0);
            $table->unsignedInteger('completed_bookings')->default(0);
            $table->unsignedInteger('total_complaints')->default(0);
            $table->unsignedInteger('total_quote_requests')->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'phone'], 'customers_clinic_phone_uq');
            $table->index(['clinic_id', 'last_interaction_at'], 'customers_clinic_last_idx');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
