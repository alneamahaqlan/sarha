<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Broadcast quote requests are not tied to a single clinic anymore.
        Schema::table('price_quote_requests', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->foreignId('clinic_id')->nullable()->change();
            $table->foreign('clinic_id')->references('id')->on('clinics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Broadcast quotes created while clinic_id was nullable would violate
        // the restored NOT NULL constraint — remove them before reverting.
        DB::table('price_quote_requests')->whereNull('clinic_id')->delete();

        Schema::table('price_quote_requests', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->foreignId('clinic_id')->nullable(false)->change();
            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
        });
    }
};
