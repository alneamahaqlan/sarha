<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_stats', function (Blueprint $table) {
            foreach (['whatsapp_clicks', 'call_clicks', 'booking_clicks'] as $col) {
                if (! Schema::hasColumn('clinic_stats', $col)) {
                    $table->unsignedInteger($col)->default(0)->after('quote_requests_count');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinic_stats', function (Blueprint $table) {
            foreach (['whatsapp_clicks', 'call_clicks', 'booking_clicks'] as $col) {
                if (Schema::hasColumn('clinic_stats', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
