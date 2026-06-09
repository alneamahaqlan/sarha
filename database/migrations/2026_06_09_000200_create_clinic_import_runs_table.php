<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per executed import (a "pull") from a saved source. Stores the
     * row range that was imported so a later re-pull of the same sheet can
     * warn about / skip rows already brought in. created_by_* mirrors the
     * polymorphic creator stamp on the bookings table (Clinic owner or
     * ClinicTeamMember).
     */
    public function up(): void
    {
        Schema::create('clinic_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_source_id')->constrained('clinic_import_sources')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_from');
            $table->unsignedInteger('row_to');
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->nullableMorphs('created_by');   // created_by_type + created_by_id
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index(['import_source_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_import_runs');
    }
};
