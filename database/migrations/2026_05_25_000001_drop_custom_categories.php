<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire the parallel "custom_categories" grouping in favour of a single
 * canonical hierarchy: complex (clinic) -> clinic (sub_clinic) -> service.
 * Demo-only data, so no migration of rows is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('services', 'custom_category_id')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropForeign(['custom_category_id']);
                $table->dropColumn('custom_category_id');
            });
        }

        Schema::dropIfExists('custom_categories');
    }

    public function down(): void
    {
        Schema::create('custom_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('emoji')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        if (! Schema::hasColumn('services', 'custom_category_id')) {
            Schema::table('services', function (Blueprint $table) {
                $table->foreignId('custom_category_id')->nullable()->after('clinic_id')
                    ->constrained('custom_categories')->nullOnDelete();
            });
        }
    }
};
