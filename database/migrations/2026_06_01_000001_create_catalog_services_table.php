<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The unified service catalog — a platform-wide dictionary of canonical
 * services ("Teeth cleaning", "Laser hair removal") that individual clinic
 * `services` rows link to. This is what makes cross-clinic price comparison
 * work: many clinic services → one canonical entry → one comparable concept.
 *
 * A row with status='pending' IS the clinic's request to add a new canonical
 * service (mirrors the category_requests pattern, but kept inline on the
 * catalog row so there's a single source of truth). Admins move it to
 * 'active' (approve) or 'rejected'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // canonical Arabic name
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->text('default_description')->nullable();

            // Default specialty for this canonical service. Nullable so a
            // pending request without a clear category can still be filed.
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            // active = visible/usable; pending = awaiting admin review;
            // rejected = declined (kept for audit, not reusable).
            $table->string('status')->default('active');

            // Provenance for pending rows: which clinic asked, who reviewed.
            $table->foreignId('requested_by_clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_services');
    }
};
