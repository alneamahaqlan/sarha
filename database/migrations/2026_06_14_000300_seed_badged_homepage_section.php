<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds a (disabled) "badged" homepage section. The admin can't create
 * sections — only edit them — so the row must exist up front. Admin then
 * picks a badge + target type and activates it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('homepage_sections')) {
            return;
        }

        if (DB::table('homepage_sections')->where('key', 'badged')->exists()) {
            return;
        }

        $sort = (int) DB::table('homepage_sections')->max('sort_order') + 1;

        DB::table('homepage_sections')->insert([
            'key'             => 'badged',
            'type'            => 'badged',
            'title_ar'        => 'مميّزون',
            'title_en'        => 'Featured',
            'is_active'       => false,
            'sort_order'      => $sort,
            'item_limit'      => 8,
            'show_on_mobile'  => true,
            'show_on_desktop' => true,
            'config'          => json_encode(['badge_key' => null, 'target_type' => 'clinic', 'layout' => 'cards']),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('homepage_sections')) {
            DB::table('homepage_sections')->where('key', 'badged')->delete();
        }
    }
};
