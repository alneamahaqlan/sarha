<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the regulatory identifiers clinics provide on registration
 * (tax number + commercial registration; license_number already exists) and
 * a reversible-encrypted copy of the login password so a super-admin can
 * reveal it from the panel and the approval email can carry it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('tax_number')->nullable()->after('license_number');
            $table->string('commercial_registration')->nullable()->after('tax_number');
            // Encrypted at rest via the model's `encrypted` cast — never the
            // plaintext on disk. Hidden from every API response; revealed only
            // through the dedicated super-admin endpoint.
            $table->text('password_plaintext')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['tax_number', 'commercial_registration', 'password_plaintext']);
        });
    }
};
