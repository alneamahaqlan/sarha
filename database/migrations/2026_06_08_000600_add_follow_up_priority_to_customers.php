<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRM "follow-up priority" — an Odoo-style 0..3 star rating the team
     * sets on a customer card:
     *   0 = عادي · 1 = يحتاج متابعة · 2 = مهتم بالخدمة · 3 = جاهز للحجز
     * Indexed so the Customers Hub can sort and filter by it.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedTinyInteger('follow_up_priority')->default(0)->after('last_interaction_type');
            $table->index(['clinic_id', 'follow_up_priority'], 'customers_clinic_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_clinic_priority_idx');
            $table->dropColumn('follow_up_priority');
        });
    }
};
