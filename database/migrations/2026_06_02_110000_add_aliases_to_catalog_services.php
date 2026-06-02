<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alternative names for a canonical catalog service. Admin-managed; used to
 * widen name matching (a clinic typing a synonym links to the same canonical
 * entry) and cross-clinic search/comparison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_services', function (Blueprint $table) {
            $table->json('aliases')->nullable()->after('name_en');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_services', function (Blueprint $table) {
            $table->dropColumn('aliases');
        });
    }
};
