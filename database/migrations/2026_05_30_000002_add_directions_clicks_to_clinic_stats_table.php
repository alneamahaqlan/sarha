<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_stats', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_stats', 'directions_clicks')) {
                $table->unsignedInteger('directions_clicks')->default(0)->after('booking_clicks');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinic_stats', function (Blueprint $table) {
            if (Schema::hasColumn('clinic_stats', 'directions_clicks')) {
                $table->dropColumn('directions_clicks');
            }
        });
    }
};
