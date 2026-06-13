<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds a 5th header chrome option, `clinic`: render the linked complex's full
 * public-profile hero (logo + stories + follow + stats + specialties + social)
 * as the landing page's header section, with a "visit the clinic page" link.
 * Footer stays on the original 4 modes (a clinic hero is a header concept).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE landing_pages MODIFY header_mode ENUM('default','minimal','custom','none','clinic') NOT NULL DEFAULT 'default'");
    }

    public function down(): void
    {
        // Demote any clinic-mode rows before shrinking the enum back.
        DB::statement("UPDATE landing_pages SET header_mode = 'default' WHERE header_mode = 'clinic'");
        DB::statement("ALTER TABLE landing_pages MODIFY header_mode ENUM('default','minimal','custom','none') NOT NULL DEFAULT 'default'");
    }
};
