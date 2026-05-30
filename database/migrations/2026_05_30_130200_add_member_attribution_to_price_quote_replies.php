<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The team member (or null if the owner) who authored the reply.
        // Snapshot the name so soft-deletion / removal of the member
        // doesn't make the reply look anonymous to the customer.
        Schema::table('price_quote_replies', function (Blueprint $table) {
            $table->foreignId('replied_by_member_id')
                ->nullable()
                ->after('clinic_id')
                ->constrained('clinic_team_members')
                ->nullOnDelete();
            $table->string('replied_by_name_snapshot')->nullable()->after('replied_by_member_id');
            $table->string('replied_by_role_snapshot', 16)->nullable()->after('replied_by_name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('price_quote_replies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replied_by_member_id');
            $table->dropColumn(['replied_by_name_snapshot', 'replied_by_role_snapshot']);
        });
    }
};
