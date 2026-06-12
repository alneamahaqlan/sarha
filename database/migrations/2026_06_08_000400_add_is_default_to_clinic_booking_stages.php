<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mark the 4 seeded base stages (one per kind) as non-deletable
     * anchors. Clinics may rename/recolour/reorder them but can only
     * delete stages they added themselves — guaranteeing every kind
     * always keeps at least one fallback column.
     */
    public function up(): void
    {
        Schema::table('clinic_booking_stages', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('color');
        });

        // Backfill: the earliest stage of each (clinic, kind) is its base.
        $ids = DB::table('clinic_booking_stages')
            ->selectRaw('MIN(id) as id')
            ->groupBy('clinic_id', 'kind')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('clinic_booking_stages')->whereIn('id', $ids)->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('clinic_booking_stages', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
