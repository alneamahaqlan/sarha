<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a free-form JSON `content` bag to clinic_page_sections.
 *
 * Most sections render data sourced from their own tables (offers,
 * services, …) so they need no stored content. The new `faqs` section is
 * the exception: its Q&A pairs have no home table, so the clinic admin
 * authors them straight into the page-builder. Keeping the bag generic
 * (rather than a dedicated clinic_faqs table) means the existing
 * toggle / reorder / title-override flow manages the section for free.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_page_sections', function (Blueprint $table) {
            $table->json('content')->nullable()->after('item_limit');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_page_sections', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
