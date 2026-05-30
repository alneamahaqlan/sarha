<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('clinic_id')
                ->constrained()
                ->nullOnDelete();
            $table->index('customer_id', 'complaints_customer_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex('complaints_customer_id_idx');
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
