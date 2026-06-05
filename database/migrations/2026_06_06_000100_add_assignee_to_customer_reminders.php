<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional team-member assignee on a contact reminder. The reminder
     * still notifies the whole clinic (shared bell + Web Push), but it
     * carries the assignee so the notification can name them and the
     * reminders page can offer an "assigned to me" filter.
     */
    public function up(): void
    {
        Schema::table('customer_reminders', function (Blueprint $table) {
            $table->foreignId('assignee_member_id')
                ->nullable()
                ->after('booking_id')
                ->constrained('clinic_team_members')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_reminders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_member_id');
        });
    }
};
