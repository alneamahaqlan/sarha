<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the foreign key linking every service to a service_categories row.
 * Nullable so the migration is safe on existing data; the seeder runs
 * right after to populate it, and StoreServiceRequest / UpdateServiceRequest
 * enforce 'required' at the API layer for any new or edited service.
 *
 * `restrictOnDelete` prevents an admin from deleting a service category
 * that still has services pointing to it — surfaced as a clear 403 in
 * ServiceCategoryController::destroy().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('service_category_id')
                ->nullable()
                ->after('sub_clinic_id')
                ->constrained('service_categories')
                ->restrictOnDelete();

            // Speed up "all clinics offering X service-category" lookups, which
            // are now the primary index for the AI search and the public
            // category-browse pages.
            $table->index(['service_category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['service_category_id']);
            $table->dropIndex(['service_category_id', 'is_active']);
            $table->dropColumn('service_category_id');
        });
    }
};
