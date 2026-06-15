<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends a badge with:
 *  - target_types: which entity kinds it may be assigned to (clinic/offer/…),
 *    constraining both the manual picker and which auto rules apply.
 *  - description_ar/en: optional explanatory copy (tooltip + homepage strip).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->json('target_types')->nullable()->after('key');
            $table->string('description_ar', 255)->nullable()->after('label_en');
            $table->string('description_en', 255)->nullable()->after('description_ar');
        });

        // Existing badges were clinic-only.
        DB::table('badges')->update(['target_types' => json_encode(['clinic'])]);
    }

    public function down(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->dropColumn(['target_types', 'description_ar', 'description_en']);
        });
    }
};
