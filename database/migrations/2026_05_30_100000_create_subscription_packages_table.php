<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per subscription tier the super-admin offers. Three rows
 * are seeded by default (Free / Standard / Premium), but the table
 * is editable — admin can add tiers, change prices, or toggle any
 * single feature without a code deploy.
 *
 * Feature columns are kept FLAT (not JSON) so:
 *   - the admin UI is one row per package with toggles + integer
 *     inputs (instead of a JSON-blob editor)
 *   - per-feature queries are indexable later if needed
 *     (e.g., "all packages where featured_in_search = true")
 *   - migrations document the feature catalogue with names + types
 *
 * Limits use INT NULL where null means "unlimited". Booleans are
 * tinyint(1). The catalogue itself doesn't change often — adding a
 * new feature column is a one-time migration the dev writes; the
 * admin then flips it per tier from the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->id();

            // ── Identity ───────────────────────────────────────────────
            $table->string('slug', 32)->unique();            // free / standard / premium (also used by legacy clinics.subscription_type)
            $table->string('name_ar');                       // مجانية / مدفوعة 1 / مدفوعة 2
            $table->string('name_en');
            $table->text('tagline_ar')->nullable();          // one-line marketing pitch
            $table->text('tagline_en')->nullable();
            $table->string('color_token', 16)->default('gray'); // gray / sage / gold — used by React badges

            // ── Pricing ────────────────────────────────────────────────
            $table->decimal('monthly_price', 10, 2)->default(0);

            // ── Display / lifecycle ────────────────────────────────────
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);     // soft-toggle: hides from clinic upgrade flow but keeps existing subs intact

            // ── 12 feature columns (kept flat — see class docblock) ────
            $table->unsignedInteger('services_limit')->nullable();              // 1 — null = unlimited
            $table->unsignedInteger('articles_monthly_limit')->nullable();      // 2 — null = unlimited
            $table->boolean('ai_article_generation')->default(false);           // 3
            $table->boolean('featured_in_search')->default(false);              // 4
            $table->unsignedTinyInteger('ai_assistant_priority')->default(0);   // 5 — 0/1/2 priority bucket in AI assistant ranking
            $table->boolean('google_reviews_sync')->default(false);             // 6
            $table->boolean('verified_badge')->default(false);                  // 7
            $table->string('analytics_level', 16)->default('basic');            // 8 — basic | full
            $table->unsignedInteger('quote_replies_monthly_limit')->nullable(); // 9 — null = unlimited
            $table->unsignedTinyInteger('banner_slots')->default(0);            // 10 — how many homepage banner slots they own
            $table->boolean('allow_offers_packages')->default(false);           // 11 — covers Offer + medical Package
            $table->boolean('allow_doctors_before_after')->default(false);      // 12 — covers Doctors + Before/After gallery

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_packages');
    }
};
