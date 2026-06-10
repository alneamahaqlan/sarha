<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super-admin built Landing Pages, served at /l/{slug}. Each page has a type
 * (clinic / offer / city / category / comparison / custom) that drives which
 * data is auto-pulled, a publish window, CTA + SEO + OpenGraph config, and a
 * set of ordered, toggleable content blocks (see landing_page_blocks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();

            $table->enum('type', ['clinic', 'offer', 'city', 'category', 'comparison', 'custom'])->index();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->string('slug')->unique();
            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->string('internal_name')->nullable();

            // Linked entity — only the column relevant to `type` is set.
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('cover_image')->nullable();
            $table->string('social_image')->nullable();

            // Publish window.
            $table->timestamp('published_at')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();

            // Primary CTA + contact buttons.
            $table->string('cta_label_ar')->nullable();
            $table->string('cta_label_en')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('cta_style')->nullable(); // book | link | scroll_booking
            $table->string('whatsapp_phone')->nullable();
            $table->string('call_phone')->nullable();
            $table->boolean('whatsapp_enabled')->default(true);
            $table->boolean('call_enabled')->default(true);

            // SEO.
            $table->string('seo_title_ar')->nullable();
            $table->string('seo_title_en')->nullable();
            $table->string('seo_description_ar', 300)->nullable();
            $table->string('seo_description_en', 300)->nullable();
            $table->string('seo_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('meta_robots')->default('index,follow');
            $table->boolean('in_sitemap')->default(true)->index();

            // OpenGraph overrides.
            $table->string('og_title_ar')->nullable();
            $table->string('og_title_en')->nullable();
            $table->string('og_description_ar', 300)->nullable();
            $table->string('og_description_en', 300)->nullable();

            // Schema.org markup — explicit JSON override or generated per type.
            $table->json('schema_markup')->nullable();
            $table->string('schema_type')->nullable();

            // Denormalized list counters (cheap badges in the admin index).
            $table->unsignedBigInteger('total_views')->default(0);
            $table->unsignedBigInteger('total_conversions')->default(0);

            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Hot path: resolving a live page by status + publish window.
            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_pages');
    }
};
