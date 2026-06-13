<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-landing-page header & footer override. By default a landing page reuses
 * the platform's global header/footer (mode = "default"). A page may instead
 * pick a "minimal" chrome (logo only), a fully "custom" chrome (own logo,
 * links, CTA, colors — stored in the *_config JSON), or "none" (hide it
 * entirely for a distraction-free conversion page).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->enum('header_mode', ['default', 'minimal', 'custom', 'none'])
                ->default('default')->after('call_enabled');
            $table->enum('footer_mode', ['default', 'minimal', 'custom', 'none'])
                ->default('default')->after('header_mode');
            $table->json('header_config')->nullable()->after('footer_mode');
            $table->json('footer_config')->nullable()->after('header_config');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn(['header_mode', 'footer_mode', 'header_config', 'footer_config']);
        });
    }
};
