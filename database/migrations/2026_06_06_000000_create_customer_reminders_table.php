<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contact reminders: a clinic schedules a "call this patient at time X"
     * nudge. A scheduler command (saerha:dispatch-contact-reminders) fires
     * a CONTACT_REMINDER_DUE notification once remind_at passes, then stamps
     * notified_at so re-runs don't double-send. Audience is the whole clinic
     * (shared bell) — created_by_* snapshots who set it.
     */
    public function up(): void
    {
        Schema::create('customer_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // Optional context: the booking/quote that prompted the reminder.
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            $table->timestamp('remind_at');
            $table->text('note')->nullable();

            // pending → done | cancelled
            $table->string('status', 16)->default('pending');

            // Who created it (Clinic owner or ClinicTeamMember) — snapshot
            // name so removing a member later keeps the row readable.
            $table->string('created_by_type')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();

            // Lifecycle stamps.
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // The scheduler's hot query: pending rows whose time has come.
            $table->index(['status', 'remind_at']);
            $table->index(['clinic_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_reminders');
    }
};
