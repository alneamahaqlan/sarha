<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-customer marketing opt-out. Customers flagged here are excluded
     * from campaign audiences (PDPL: a clinic must honour "do not contact").
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('marketing_opt_out')->default(false)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('marketing_opt_out');
        });
    }
};
