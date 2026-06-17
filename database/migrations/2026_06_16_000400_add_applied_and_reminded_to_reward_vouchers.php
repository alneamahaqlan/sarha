<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two additive columns for the redemption flow:
 *
 *  - applied_booking_id: the "reserve" link. A customer who applies a
 *    voucher when booking the linked offer/service stamps this WITHOUT
 *    consuming it (status stays active); reception redeems it to `used`
 *    at the visit. Lets the booking show its discounted effect while the
 *    voucher is only spent on actual attendance.
 *
 *  - expiry_reminded_at: idempotency stamp for the expiry-reminder
 *    command (mirrors customer_reminders.notified_at) so the daily run
 *    never double-nudges.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_vouchers', function (Blueprint $table) {
            $table->foreignId('applied_booking_id')->nullable()->after('redeemed_booking_id')
                ->constrained('bookings')->nullOnDelete();
            $table->timestamp('expiry_reminded_at')->nullable()->after('used_at');
        });
    }

    public function down(): void
    {
        Schema::table('reward_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('applied_booking_id');
            $table->dropColumn('expiry_reminded_at');
        });
    }
};
