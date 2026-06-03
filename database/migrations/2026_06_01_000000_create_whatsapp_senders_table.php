<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp sender numbers (Wappi profiles) used to deliver OTP codes.
 *
 * Each row is one WhatsApp number connected through the gateway and carries
 * its own credentials (profile_id + token). The OTP dispatcher rotates across
 * the active rows so no single number is rate-limited or banned. The panel
 * caps the catalogue at 5 numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_senders', function (Blueprint $table) {
            $table->id();
            $table->string('label')->nullable();           // friendly name shown in the panel
            $table->string('phone')->unique();             // normalised international digits (e.g. 966564844382)
            $table->string('provider')->default('wappi');  // gateway driver key
            $table->string('profile_id')->nullable();      // Wappi profile id
            $table->text('token')->nullable();             // Wappi authorization token (encrypted at rest)
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0); // lower = preferred
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_senders');
    }
};
