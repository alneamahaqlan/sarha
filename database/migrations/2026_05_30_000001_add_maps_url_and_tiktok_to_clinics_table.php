<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            // Direct Google Maps link the complex admin pastes — most reliable
            // source for the "Directions" button (falls back to place_id /
            // lat-lng / address when empty).
            if (! Schema::hasColumn('clinics', 'maps_url')) {
                $table->string('maps_url')->nullable()->after('google_place_id');
            }
            // TikTok handle/URL for the social bar.
            if (! Schema::hasColumn('clinics', 'tiktok')) {
                $table->string('tiktok')->nullable()->after('snapchat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            foreach (['maps_url', 'tiktok'] as $col) {
                if (Schema::hasColumn('clinics', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
