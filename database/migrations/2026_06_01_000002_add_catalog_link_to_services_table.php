<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Link clinic services to the unified catalog, and add a moderation gate.
 *
 *  - catalog_service_id : which canonical service this is an instance of
 *    (nullable — legacy rows stay unlinked until the Phase-4 clustering
 *    migration backfills them).
 *  - approval_status : 'approved' lets the service show publicly; 'pending'
 *    hides it until an admin approves the catalog request it belongs to.
 *
 * CRITICAL: every existing row is backfilled to 'approved' so nothing
 * currently live disappears the moment this migration runs. Only NEW
 * services created through the match-or-request flow can be 'pending'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('catalog_service_id')
                ->nullable()
                ->after('clinic_id')
                ->constrained('catalog_services')
                ->nullOnDelete();

            $table->string('approval_status')
                ->default('approved')      // new rows default to approved...
                ->after('is_active');

            $table->index(['approval_status', 'is_active']);
        });

        // ...and existing rows are explicitly approved too (defensive — the
        // column default already covers them, but make intent unmistakable).
        DB::table('services')->update(['approval_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['catalog_service_id']);
            $table->dropIndex(['approval_status', 'is_active']);
            $table->dropColumn(['catalog_service_id', 'approval_status']);
        });
    }
};
