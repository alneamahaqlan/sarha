<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Patient re-engagement campaigns (v1 = manual per-recipient WhatsApp +
     * tracking; no bulk auto-send).
     *
     *   clinic_campaigns   — name + message template + audience snapshot.
     *   campaign_recipients — the resolved audience, snapshotted at creation
     *     so later segment changes don't mutate the campaign; each row tracks
     *     its own send status.
     */
    public function up(): void
    {
        Schema::create('clinic_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name');
            // Message body; supports the {{name}} placeholder rendered per recipient.
            $table->text('message_template');
            // Filter snapshot: {segment, booking_range, last_interaction, has_notes, search}.
            $table->json('audience')->nullable();
            // draft | active | completed
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->string('created_by_type')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('clinic_campaigns')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            // Snapshots so the row stays readable if the customer is renamed/removed.
            $table->string('name_snapshot')->nullable();
            $table->string('phone_snapshot')->nullable();
            // pending | sent | skipped
            $table->string('status', 16)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->unique(['campaign_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('clinic_campaigns');
    }
};
