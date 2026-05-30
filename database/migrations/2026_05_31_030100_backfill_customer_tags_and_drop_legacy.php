<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copy every row from the phone-keyed `booking_customer_tags` into
 * the new FK'd `customer_tags`, then drop the legacy table.
 *
 * The JOIN works because phase 1's CustomersBackfillSeeder already
 * normalized every customer_phone string in this table (see
 * CustomersBackfillSeeder::normalizePhoneColumns) AND populated a
 * Customer row for every (clinic_id, phone) pair that had any
 * interaction — including tag-only customers? No, tags piggyback on
 * bookings, so every customer in booking_customer_tags must have a
 * booking → a Customer row. The JOIN below catches them.
 *
 * INSERT IGNORE for the rare case where the same (customer_id,
 * label) pair already exists (re-running the migration on a
 * partially-migrated DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_customer_tags')) {
            return; // already migrated
        }

        // Single SQL pass — no risk of N+1 even at large volumes.
        // INSERT IGNORE INTO customer_tags
        //   (customer_id, label, color, created_by_*, created_at, updated_at)
        // SELECT customers.id, bct.label, bct.color, bct.created_by_type,
        //        bct.created_by_id, bct.created_at, bct.updated_at
        // FROM booking_customer_tags bct
        // JOIN customers
        //   ON customers.clinic_id = bct.clinic_id
        //  AND customers.phone     = bct.customer_phone
        DB::statement(<<<SQL
            INSERT IGNORE INTO customer_tags
                (customer_id, label, color, created_by_type, created_by_id, created_at, updated_at)
            SELECT
                customers.id,
                bct.label,
                bct.color,
                bct.created_by_type,
                bct.created_by_id,
                bct.created_at,
                bct.updated_at
            FROM booking_customer_tags bct
            INNER JOIN customers
                ON customers.clinic_id = bct.clinic_id
               AND customers.phone     = bct.customer_phone
        SQL);

        Schema::drop('booking_customer_tags');
    }

    public function down(): void
    {
        // Recreate the legacy table shape (phase 1's schema) so a
        // rollback chain can re-run the earlier migrations. We do NOT
        // backfill the rows on rollback — the data lives in
        // customer_tags now.
        Schema::create('booking_customer_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('customer_phone', 32);
            $table->string('label', 60);
            $table->string('color', 16);
            $table->nullableMorphs('created_by');
            $table->timestamps();

            $table->unique(['clinic_id', 'customer_phone', 'label'], 'cust_tags_clinic_phone_label_uq');
            $table->index(['clinic_id', 'customer_phone'], 'cust_tags_clinic_phone_idx');
        });
    }
};
