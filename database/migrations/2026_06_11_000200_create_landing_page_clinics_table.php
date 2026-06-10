<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ordered set of complexes shown side-by-side on a `comparison` landing
 * page. `highlight` marks the recommended column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_clinics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('highlight')->default(false);
            $table->timestamps();

            $table->unique(['landing_page_id', 'clinic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_clinics');
    }
};
