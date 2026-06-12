<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daily aggregate, consistent with the other clinic_stats counters
        // (page_views, whatsapp_clicks, …). Powers the stats centers.
        Schema::table('clinic_stats', function (Blueprint $table) {
            $table->unsignedInteger('story_views')->default(0)->after('directions_clicks');
        });

        // Per-story lifetime counter shown above each story in the panel.
        Schema::table('clinic_stories', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0)->after('caption');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_stats', function (Blueprint $table) {
            $table->dropColumn('story_views');
        });
        Schema::table('clinic_stories', function (Blueprint $table) {
            $table->dropColumn('views_count');
        });
    }
};
