<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a second campaign fulfilment mode: "managed".
 *
 * `self` (default) is the existing manual flow — the clinic sends each
 * WhatsApp itself and marks recipients. `managed` is a request handed to
 * the platform: the clinic prepares image + text + audience and submits;
 * platform staff run it OUTSIDE the system (contact, pricing, execution
 * are all external). The system only intakes the request, exports the
 * recipient data, and lets an admin close the queue item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_campaigns', function (Blueprint $table) {
            $table->string('type', 16)->default('self')->after('clinic_id');
            // Campaign creative image (managed campaigns); relative storage path.
            $table->string('image_path')->nullable()->after('message_template');
            // Admin-queue state — only meaningful when type = managed.
            $table->string('managed_status', 16)->nullable()->after('status');
            $table->timestamp('managed_closed_at')->nullable()->after('managed_status');
            $table->foreignId('managed_closed_by_admin_id')->nullable()
                ->after('managed_closed_at')
                ->constrained('admins')->nullOnDelete();

            $table->index(['type', 'managed_status']);
        });
    }

    public function down(): void
    {
        Schema::table('clinic_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('managed_closed_by_admin_id');
            $table->dropIndex(['type', 'managed_status']);
            $table->dropColumn(['type', 'image_path', 'managed_status', 'managed_closed_at']);
        });
    }
};
