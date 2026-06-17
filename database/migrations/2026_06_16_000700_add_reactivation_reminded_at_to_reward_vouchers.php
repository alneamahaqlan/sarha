<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency + cooldown stamp for the reactivation reminder (a nudge to
 * holders of an idle, still-unused voucher). Distinct from
 * expiry_reminded_at: that fires once near expiry; this fires for idle
 * vouchers that are NOT near expiry, on a cooldown, to coax a return
 * visit without nagging.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_vouchers', function (Blueprint $table) {
            $table->timestamp('reactivation_reminded_at')->nullable()->after('expiry_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('reward_vouchers', function (Blueprint $table) {
            $table->dropColumn('reactivation_reminded_at');
        });
    }
};
