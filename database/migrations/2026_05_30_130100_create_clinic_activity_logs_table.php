<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parallel to audit_logs but scoped to clinic-internal team
        // activity. Audience: the clinic owner (sees their team's actions).
        // Actor is polymorphic because both the owner (Clinic) and team
        // members (ClinicTeamMember) can trigger events.
        Schema::create('clinic_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            // morphTo actor (Clinic or ClinicTeamMember). Nullable so a
            // soft-deleted member doesn't orphan its history.
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            // Snapshot of the actor's display name at the time of the
            // event — survives soft-deletes so the row stays readable.
            $table->string('actor_name');
            // Role at the time of the event (owner|coordinator|reception)
            // so the activity table can show "Mohammed (coordinator)"
            // without re-resolving the actor.
            $table->string('actor_role', 16)->nullable();
            // Action codes match the audit_logs convention:
            // "booking.confirmed", "service.created", etc.
            $table->string('action');
            // Polymorphic target — what the action operated on.
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            // Free-form summary payload: reference codes, before/after
            // values, anything the UI needs to render the row label.
            $table->json('summary')->nullable();
            $table->string('ip_address', 45)->nullable();
            // Immutable: created_at only (no updated_at) — events are
            // facts, not editable records.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['clinic_id', 'created_at']);
            $table->index(['actor_type', 'actor_id']);
            $table->index(['model_type', 'model_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_activity_logs');
    }
};
