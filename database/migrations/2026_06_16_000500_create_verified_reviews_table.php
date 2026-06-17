<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verified reviews — the trust layer. A review is VERIFIED because it is
 * tied to a booking with a confirmed attendance (bookings.attended_at):
 * only a patient who actually showed up can rate the visit. One review
 * per attended booking (unique booking_id).
 *
 * Separate aspects: clinic_rating (the visit/complex experience) +
 * doctor_rating (the doctor seen, attributed to doctor_id when known).
 *
 * Non-coercive by design: there is NO review-gating. A real negative is
 * stored and shown exactly like a positive; is_visible exists ONLY for
 * admin moderation of spam/abuse (phase 3), never to hide a genuine
 * negative. The clinic replies publicly (Complaint-style reply fields).
 *
 * Lifecycle: a row is created `pending` when attendance is confirmed
 * (phase-2 listener, eligibility); the patient later submits ratings +
 * comment → `published`. Standalone model — unrelated to GoogleReview
 * sync, though both surface on the public profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verified_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 16)->unique();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            // The attended visit this review is verified against. Unique →
            // one review per booking; cascade so a hard-deleted booking
            // can't leave an unverifiable orphan.
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The doctor the doctor_rating is about (when the patient
            // identifies one / the clinic has doctors). Nullable.
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();

            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();

            // Separate aspects — null until the patient submits.
            $table->unsignedTinyInteger('clinic_rating')->nullable();
            $table->unsignedTinyInteger('doctor_rating')->nullable();
            $table->text('comment')->nullable();

            // pending = eligible/awaiting submission · published = submitted.
            $table->enum('status', ['pending', 'published'])->default('pending');
            // Moderation flag ONLY (spam/abuse) — never used to bury a real
            // negative. Default visible.
            $table->boolean('is_visible')->default(true);

            // Public clinic reply (mirrors Complaint's dual-reply snapshots).
            $table->text('clinic_reply_text')->nullable();
            $table->foreignId('clinic_replied_by_member_id')->nullable()
                ->constrained('clinic_team_members')->nullOnDelete();
            $table->string('clinic_replied_by_name_snapshot')->nullable();
            $table->string('clinic_replied_by_role_snapshot')->nullable();
            $table->timestamp('clinic_replied_at')->nullable();

            // Invitation idempotency (phase-2 scheduled command) + submission stamp.
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->index(['clinic_id', 'status', 'is_visible']);
            $table->index('doctor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verified_reviews');
    }
};
