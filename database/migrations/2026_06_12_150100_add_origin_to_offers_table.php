<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguish offers created from the inline "سعر العرض" field on the service
 * form ('service_form') from offers built on the dedicated Offers page
 * ('clinic'). The service form upserts exactly the one 'service_form' offer
 * per service, so it never clobbers a manually-managed offer for the same
 * service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('origin')->default('clinic')->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
