<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-case display method on the public clinic page:
     *  - side_by_side: the current layout (before / after shown next to each other)
     *  - slider:       an interactive draggable vertical divider over stacked images
     */
    public function up(): void
    {
        Schema::table('before_after_photos', function (Blueprint $table) {
            $table->string('display_mode', 20)->default('side_by_side')->after('after_image');
        });
    }

    public function down(): void
    {
        Schema::table('before_after_photos', function (Blueprint $table) {
            $table->dropColumn('display_mode');
        });
    }
};
