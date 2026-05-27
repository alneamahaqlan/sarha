<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service categories — admin-managed lookup that every individual service
 * must reference. Distinct from `categories` (which groups CLINICS by
 * specialty, e.g. "Dentistry"); a service category groups SERVICES by
 * procedure type, e.g. "تنظيف وتبييض"، "ليزر"، "زراعة"، "حقن تجميل".
 *
 * Created here as a standalone lookup with the same shape as `categories`:
 * Arabic + English name, slug, icon/emoji, active flag, sort order. Admin
 * UI mirrors the existing category management screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // Arabic display name (required)
            $table->string('name_en')->nullable();     // English equivalent
            $table->string('slug')->unique();
            $table->string('icon')->nullable();        // optional Lucide / emoji glyph
            $table->string('emoji')->nullable();
            $table->text('description')->nullable();   // short admin-facing description
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
