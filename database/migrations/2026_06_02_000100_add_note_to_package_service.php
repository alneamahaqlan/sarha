<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-service note inside a package — e.g. "includes a discount card
     * for the dermatology section". Lets the complex attach a short
     * description to each included service rather than just listing it.
     */
    public function up(): void
    {
        Schema::table('package_service', function (Blueprint $table) {
            $table->string('note', 255)->nullable()->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('package_service', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
