<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `last_reminder_at` marks when the automatic abandoned-cart reminder
     * (WhatsApp + WebPush) last fired for a cart row, so the scheduled
     * command stays idempotent and never re-nudges the same item.
     * (`booked_at` already exists — it is now stamped on book-from-cart.)
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->timestamp('last_reminder_at')->nullable()->after('booked_at');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('last_reminder_at');
        });
    }
};
