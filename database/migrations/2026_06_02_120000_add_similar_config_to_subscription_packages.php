<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-package config for the public "similar / related" strips. JSON so the
 * shape can evolve without a column-per-toggle explosion. NULL = use the
 * model's sensible defaults (all sections on, match city + specialty, 6
 * cards). Read by App\Services\SimilarityService via the page-owner clinic's
 * package, edited in the super-admin package dialog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->json('similar_config')->nullable()->after('allow_doctors_before_after');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->dropColumn('similar_config');
        });
    }
};
