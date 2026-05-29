<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Now that Offer is a standalone entity, the embedded offer fields on
 * services no longer have a job. Drop them so the services table stops
 * carrying promo state that belongs elsewhere.
 *
 * Per spec: all existing data is seeded/demo, so a destructive drop is
 * safe. Seeders are updated in the same release to populate Offer rows
 * instead of these fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['old_price', 'offer_expires_at', 'is_featured_offer']);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('old_price', 10, 2)->nullable();
            $table->dateTime('offer_expires_at')->nullable();
            $table->boolean('is_featured_offer')->default(false);
        });
    }
};
