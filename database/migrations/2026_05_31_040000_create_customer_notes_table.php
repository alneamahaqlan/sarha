<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-note thread on the Customer entity, used by the Customer
 * Hub profile page + a condensed thread inside the Kanban side
 * panel. Replaces the single-text-field Customer.notes column from
 * phase 2 (migrated + dropped in the next migration).
 *
 * Author morphs match the actor pattern used by ClinicActivityLog
 * (Clinic owner or ClinicTeamMember). created_by_name is a snapshot
 * so removing a team member doesn't blank out their authored notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->nullableMorphs('created_by');
            $table->string('created_by_name')->default('—');
            $table->timestamps();

            // Profile + side-panel queries always sort pinned-first
            // then created_at desc — this index serves both.
            $table->index(['customer_id', 'is_pinned', 'created_at'], 'cn_customer_pinned_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notes');
    }
};
