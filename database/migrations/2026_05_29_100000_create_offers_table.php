<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone Offers — what used to live as fields (old_price,
 * offer_expires_at, is_featured_offer) on the services row is now its own
 * entity. This gives the clinic admin a dedicated page builder for
 * promotions and unlocks "general" offers that aren't tied to one
 * specific service (Ramadan sales, bundle promos, etc.).
 *
 * Two kinds:
 *  - type='service'  → linked to a specific Service. CTA is "احجز هذه
 *    الخدمة" deep-linking to the booking form.
 *  - type='general'  → standalone promo. CTA is "تواصل للاستفسار" routing
 *    the visitor to call/WhatsApp.
 *
 * Visibility is derived from is_active + the [starts_at, ends_at] window —
 * the public page only ever shows currently-running active offers. Past
 * offers are retained for the clinic admin's history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            // service_id nullable — set to null for type='general'. ON
            // DELETE SET NULL so soft-deleting the service hides the offer
            // from the public side without losing it from the admin view
            // (the controller filters out offers whose service_id is
            // present but model resolves to null).
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['service', 'general']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image')->nullable();                  // storage/public path
            $table->decimal('old_price', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Hot path: rendering a clinic's active offers list.
            $table->index(['clinic_id', 'is_active', 'starts_at', 'ends_at'], 'offers_render_idx');
            // Hot path: featured offers across the platform (homepage).
            $table->index(['is_featured', 'is_active'], 'offers_featured_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
