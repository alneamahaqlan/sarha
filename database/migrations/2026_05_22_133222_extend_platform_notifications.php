<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_notifications', 'icon')) {
                $table->string('icon')->nullable()->after('type');
            }
            if (! Schema::hasColumn('platform_notifications', 'url')) {
                $table->string('url')->nullable()->after('icon');
            }
            if (! Schema::hasColumn('platform_notifications', 'priority')) {
                $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->after('url');
            }
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'pn_recipient_read');
        });
    }

    public function down(): void
    {
        Schema::table('platform_notifications', function (Blueprint $table) {
            $table->dropIndex('pn_recipient_read');
            $table->dropColumn(['icon', 'url', 'priority']);
        });
    }
};
