<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web Push subscriptions, one row per (user × browser × device).
 * Polymorphic to subscribable so the same table backs admins, clinic
 * owners, and customers — the broadcast layer already speaks that
 * shape via PlatformNotification.
 *
 * `endpoint` is the unique identity assigned by the browser's Push
 * service (FCM, Mozilla autopush, Apple). It naturally dedupes —
 * a subscribe call from a tab that already exists returns the same
 * endpoint, so we upsert on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->morphs('subscribable');                 // polymorphic to Admin/Clinic/User
            $table->text('endpoint');                       // Push service URL (long, encrypted)
            $table->string('p256dh_key', 88);               // public key for envelope encryption
            $table->string('auth_key', 24);                 // auth secret for envelope encryption
            $table->string('content_encoding', 16)->default('aesgcm');
            $table->string('user_agent', 255)->nullable();  // for debug + UI ("Chrome on Mac")
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Endpoint is the canonical identity — same browser tab
            // re-subscribing returns the same endpoint, so we unique
            // on its hash to avoid duplicates AND let the upsert run.
            // The hash column is generated to keep the index size sane.
            $table->string('endpoint_hash', 64)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
