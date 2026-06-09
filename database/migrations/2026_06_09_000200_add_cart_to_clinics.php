<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-clinic "cart" feature gate, mirroring the tracking-pixels pattern:
     * the clinic requests activation and the super-admin approves / rejects /
     * disables. The public "add to cart" button + endpoints only work when a
     * clinic's `cart_status` is 'active'.
     *
     * `cart_storefront_enabled` (Phase 2) lets an active clinic show/hide the
     * cart on its own storefront — the public button needs BOTH active status
     * AND this flag on. Added now so a single migration covers both phases.
     */
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            // disabled (default) | pending (clinic requested) | active | rejected
            $table->string('cart_status', 16)->default('disabled')->after('tracking_reviewed_by');
            $table->text('cart_rejection_reason')->nullable()->after('cart_status');
            $table->timestamp('cart_requested_at')->nullable()->after('cart_rejection_reason');
            $table->unsignedBigInteger('cart_reviewed_by')->nullable()->after('cart_requested_at');
            // Phase 2: clinic's own show/hide switch (only meaningful while active).
            $table->boolean('cart_storefront_enabled')->default(true)->after('cart_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn([
                'cart_status',
                'cart_rejection_reason',
                'cart_requested_at',
                'cart_reviewed_by',
                'cart_storefront_enabled',
            ]);
        });
    }
};
