<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Nullable for the transition window — every existing
            // booking gets backfilled via CustomersBackfillSeeder.
            $table->foreignId('customer_id')
                ->nullable()
                ->after('clinic_id')
                ->constrained()
                ->nullOnDelete();
            $table->index('customer_id', 'bookings_customer_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_customer_id_idx');
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
