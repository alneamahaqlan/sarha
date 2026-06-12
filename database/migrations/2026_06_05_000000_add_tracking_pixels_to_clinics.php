<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-clinic marketing tracking pixels (Meta / GA4 / Google Ads /
     * Snapchat / TikTok / X). The clinic enters pixel IDs as a draft and
     * requests activation; the super-admin approves/rejects. Tracking is
     * only ever injected when the global feature flag is on AND this
     * clinic's status is 'active'.
     *
     * SENSITIVE: see docs/tracking/gtm-integration-plan.md. We never load
     * a third-party GTM container — only vetted, server-rendered pixel
     * snippets — and we never pass PII / the medical service to a pixel.
     */
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            // disabled (default) | pending (clinic requested) | active | rejected
            $table->string('tracking_status', 16)->default('disabled')->after('booking_stage_labels');

            // [{ "provider": "meta", "id": "123456789", "enabled": true }, ...]
            $table->json('tracking_pixels')->nullable()->after('tracking_status');

            // Layer B: send HASHED phone/email for advanced matching. Off by
            // default, requires explicit clinic opt-in + visitor consent.
            $table->boolean('advanced_matching_optin')->default(false)->after('tracking_pixels');

            // Moderation trail.
            $table->text('tracking_rejection_reason')->nullable()->after('advanced_matching_optin');
            $table->timestamp('tracking_requested_at')->nullable()->after('tracking_rejection_reason');
            $table->unsignedBigInteger('tracking_reviewed_by')->nullable()->after('tracking_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn([
                'tracking_status',
                'tracking_pixels',
                'advanced_matching_optin',
                'tracking_rejection_reason',
                'tracking_requested_at',
                'tracking_reviewed_by',
            ]);
        });
    }
};
