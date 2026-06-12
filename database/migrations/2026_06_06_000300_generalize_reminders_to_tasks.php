<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generalise contact reminders into a unified tasks-and-reminders model:
     *   - `title`: free-text subject for a general team task.
     *   - `customer_id` becomes nullable — a task need not be tied to a
     *     customer (e.g. "restock supplies", "call back the supplier").
     *
     * Customer-linked rows keep their existing behaviour; rows with no
     * customer are general tasks driven by their title.
     */
    public function up(): void
    {
        // Drop the cascade FK so we can relax the column to nullable.
        Schema::table('customer_reminders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        Schema::table('customer_reminders', function (Blueprint $table) {
            $table->string('title')->nullable()->after('booking_id');
            $table->unsignedBigInteger('customer_id')->nullable()->change();
            // Re-add as nullOnDelete: deleting a customer detaches the task
            // (it survives as a general task) rather than destroying it.
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_reminders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('title');
        });
        Schema::table('customer_reminders', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }
};
