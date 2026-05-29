<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds "book for a relative" support:
 *
 *  - relatives: up to 3 active rows per user, each carrying a patient
 *    name, relationship type, and a Saudi mobile number. Soft-deleted so
 *    historic bookings keep their reference even after the relative is
 *    removed from the booker's saved list.
 *
 *  - bookings.booker_user_id: when set, the booking was placed by a
 *    different account holder on behalf of the patient. user_id stays
 *    as the booking owner (the account where it shows up). Notifications
 *    always route to booker_user_id (or user_id when null).
 *
 *  - bookings.relative_id: when set, the patient is one of the booker's
 *    saved relatives. customer_name/customer_phone keep mirroring the
 *    patient so existing read paths (clinic admin, exports) keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Slug-style key from a fixed list (father, mother, …). The
            // free-text label below carries the human label when the
            // booker picked "other".
            $table->string('relationship_type', 32);
            $table->string('relationship_label')->nullable();
            $table->string('phone', 20);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'deleted_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('booker_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('relative_id')
                ->nullable()
                ->after('booker_user_id')
                ->constrained('relatives')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['relative_id']);
            $table->dropForeign(['booker_user_id']);
            $table->dropColumn(['relative_id', 'booker_user_id']);
        });

        Schema::dropIfExists('relatives');
    }
};
