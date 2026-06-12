<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-clinic gate on REPLYING to broadcast price-quote requests, mirroring
     * the cart pattern. Unlike cart, the default is 'active': every clinic can
     * reply out of the box, and the super-admin can disable a clinic at will.
     * A disabled/rejected clinic still SEES the requests but cannot reply until
     * it requests activation and an admin approves.
     *
     * Hiding the customer's phone from clinics is handled in the resource layer,
     * not here — no schema change needed for that.
     */
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            // active (default) | disabled (admin blocked) | pending (clinic requested) | rejected
            $table->string('price_quote_status', 16)->default('active')->after('cart_storefront_enabled');
            $table->text('price_quote_rejection_reason')->nullable()->after('price_quote_status');
            $table->timestamp('price_quote_requested_at')->nullable()->after('price_quote_rejection_reason');
            $table->unsignedBigInteger('price_quote_reviewed_by')->nullable()->after('price_quote_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn([
                'price_quote_status',
                'price_quote_rejection_reason',
                'price_quote_requested_at',
                'price_quote_reviewed_by',
            ]);
        });
    }
};
