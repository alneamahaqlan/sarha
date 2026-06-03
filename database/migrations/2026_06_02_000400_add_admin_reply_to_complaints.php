<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a platform admin reply directly to the customer who filed a
     * complaint (separate from the clinic's reply). The customer sees this
     * on their /account/complaints page.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->text('admin_reply_text')->nullable()->after('clinic_replied_at');
            $table->timestamp('admin_replied_at')->nullable()->after('admin_reply_text');
            $table->foreignId('admin_replied_by_admin_id')->nullable()->after('admin_replied_at')
                ->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_replied_by_admin_id');
            $table->dropColumn(['admin_reply_text', 'admin_replied_at']);
        });
    }
};
