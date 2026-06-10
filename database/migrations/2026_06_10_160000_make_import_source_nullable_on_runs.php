<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File imports (uploaded CSV/XLSX) have no re-pullable saved source, so a
 * run may legitimately carry no import_source_id. Relax the FK to nullable
 * (keep the cascade-on-delete for the rows that DO have a source).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_import_runs', function (Blueprint $table) {
            $table->foreignId('import_source_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clinic_import_runs', function (Blueprint $table) {
            $table->foreignId('import_source_id')->nullable(false)->change();
        });
    }
};
