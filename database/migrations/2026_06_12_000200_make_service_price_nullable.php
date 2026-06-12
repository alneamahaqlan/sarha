<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow services.price to be NULL so the "خدمات أخرى" catch-all carries no
 * price. A null price is automatically skipped by every "starts from"
 * min_price query (they all filter whereNotNull('price')), so the catch-all
 * never drags a clinic's displayed minimum down to zero. Existing catch-all
 * rows (backfilled at price 0 by the previous migration) are nulled here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->change();
        });

        DB::table('services')->where('is_catchall', true)->update(['price' => null]);
    }

    public function down(): void
    {
        // Restore a concrete price on catch-all rows before re-imposing NOT NULL.
        DB::table('services')->where('is_catchall', true)->whereNull('price')->update(['price' => 0]);

        Schema::table('services', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable(false)->change();
        });
    }
};
