<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homepage CMS — every section on / is a row in `homepage_sections`. Admin
 * reorders, toggles, edits titles/limits, and schedules sections from the
 * React panel; the public Blade view loops the active list and renders each
 * via a per-type partial.
 *
 * `type` decides which partial renders the row (hero, stats, banner,
 * offers, articles, categories, category_offers, ai_highlight,
 * how_it_works, clinic_list, map, cta). `config` carries type-specific
 * settings (e.g. category_slug, clinic_list source, banner interval).
 *
 * Banner-type rows own a `homepage_banner_slides` collection — each slide
 * is image + optional link, displayed as an auto-rotating carousel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                 // stable identifier (banner, offers, ...)
            $table->string('type');                          // partial type — drives rendering
            $table->string('title_ar')->nullable();          // override default partial title; null = use lang file
            $table->string('title_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('item_limit')->nullable();           // 4/6/8/12 — null = partial default
            $table->unsignedSmallInteger('banner_interval_seconds')->nullable(); // banner only — 3/5/7
            $table->boolean('show_on_mobile')->default(true);
            $table->boolean('show_on_desktop')->default(true);
            $table->dateTime('starts_at')->nullable();        // scheduling window — null = always
            $table->dateTime('ends_at')->nullable();
            $table->json('config')->nullable();               // type-specific extras (category_slug, source, ...)
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('homepage_banner_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homepage_section_id')->constrained()->cascadeOnDelete();
            $table->string('image');                          // /storage path (jpg/png/gif/webp)
            $table->string('link_url')->nullable();           // optional click target
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['homepage_section_id', 'is_active', 'sort_order'], 'banner_slides_render_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_banner_slides');
        Schema::dropIfExists('homepage_sections');
    }
};
