<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks how many wrong codes a user has entered against a single OTP. Once it
 * reaches OtpCode::MAX_ATTEMPTS the code is burned and the user must request a
 * fresh one — stops brute-forcing a 6-digit code on a still-valid record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('is_used');
        });
    }

    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });
    }
};
