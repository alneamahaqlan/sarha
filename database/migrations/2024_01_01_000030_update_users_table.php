<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->unique()->after('email')->nullable();
            $table->string('email')->nullable()->change();
            $table->dropColumn(['password', 'email_verified_at']);
            $table->string('otp_code')->nullable()->after('phone');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            $table->boolean('is_active')->default(true)->after('otp_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'otp_code', 'otp_expires_at', 'is_active']);
            $table->string('password')->after('email');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
    }
};
