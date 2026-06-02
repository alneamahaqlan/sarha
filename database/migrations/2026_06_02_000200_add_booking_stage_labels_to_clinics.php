<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-clinic custom names for the 4 Kanban stages. The underlying
     * workflow statuses stay fixed (auto-tags/suggestions depend on
     * them) — this only overrides the displayed column labels, e.g.
     * "جديد" → "طلبات جديدة". Shape: {"new":"...","confirmed":"...",
     * "completed":"...","cancelled":"..."}. Null/absent keys fall back
     * to the default translation.
     */
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->json('booking_stage_labels')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn('booking_stage_labels');
        });
    }
};
