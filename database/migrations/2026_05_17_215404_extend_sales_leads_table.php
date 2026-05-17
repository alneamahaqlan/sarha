<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_leads', 'license_number')) {
                $table->string('license_number')->nullable()->after('email');
            }
            if (! Schema::hasColumn('sales_leads', 'district')) {
                $table->string('district')->nullable()->after('city_id');
            }
            if (! Schema::hasColumn('sales_leads', 'address')) {
                $table->string('address')->nullable()->after('district');
            }
            if (! Schema::hasColumn('sales_leads', 'last_contact_at')) {
                $table->timestamp('last_contact_at')->nullable()->after('next_follow_up_at');
            }
            if (! Schema::hasColumn('sales_leads', 'sales_notes')) {
                $table->text('sales_notes')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_leads', function (Blueprint $table) {
            $table->dropColumn(['license_number', 'district', 'address', 'last_contact_at', 'sales_notes']);
        });
    }
};
