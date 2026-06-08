<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-clinic custom Kanban stages ("الحل الوسط").
     *
     * Each stage is a column the clinic sees on the board, but it is
     * anchored to one of the 4 fixed semantic "kinds" (new / confirmed /
     * completed / cancelled). All booking business logic keeps reading
     * the immutable bookings.status; the kind is what ties a custom
     * stage back to that status family, so adding/removing stages never
     * breaks stats, suggestions, or the completion/cancellation flows.
     *
     * `bookings.stage_id` records which custom stage a booking currently
     * sits in. When null, the board falls back to the primary stage of
     * the booking's status kind — so existing rows and status-only edits
     * keep working without a backfill.
     */
    public function up(): void
    {
        Schema::create('clinic_booking_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 40);
            // One of: new | confirmed | completed | cancelled (Booking kanban kinds).
            $table->string('kind', 16);
            $table->string('color', 16)->default('slate');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['clinic_id', 'sort_order'], 'cbs_clinic_order_idx');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('stage_id')->nullable()->after('status')
                ->constrained('clinic_booking_stages')->nullOnDelete();
            $table->index(['clinic_id', 'stage_id'], 'bookings_clinic_stage_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_clinic_stage_idx');
            $table->dropConstrainedForeignId('stage_id');
        });

        Schema::dropIfExists('clinic_booking_stages');
    }
};
