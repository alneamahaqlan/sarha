<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinic Page Builder — every visual section on /clinic/{slug} is a row here,
 * scoped to one clinic. Mirrors the Homepage CMS pattern but per-clinic:
 *
 *  - `key` enumerates the 14 fixed section types (hero, offers, services,
 *    sub_clinics, doctors, before_after, google_reviews, articles, about,
 *    contact_info, working_hours, social_links, similar_services,
 *    floating_ctas). The set is fixed — admins toggle/reorder, never create.
 *  - `is_active` / `sort_order` drive visibility + ordering.
 *  - `title_ar` / `title_en` override the default section headings.
 *  - `item_limit` caps items where the section renders a collection
 *    (offers/articles/before-after/reviews/similar-services).
 *
 * Rows are lazy-seeded the first time a clinic's page builder is fetched
 * (no backfill migration needed for the X existing clinics).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('key');                                     // hero, offers, ... (one of the 14)
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('title_ar')->nullable();                    // null = use default lang key
            $table->string('title_en')->nullable();
            $table->unsignedSmallInteger('item_limit')->nullable();    // null = partial's default
            $table->timestamps();

            $table->unique(['clinic_id', 'key']);
            $table->index(['clinic_id', 'is_active', 'sort_order'], 'cps_render_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_page_sections');
    }
};
