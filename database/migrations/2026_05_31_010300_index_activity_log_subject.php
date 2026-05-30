<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Kanban Timeline reads ClinicActivityLog filtered by
 * (subject_type, subject_id) per booking; without this index the
 * full table scan grows linearly with audit volume.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_activity_logs', function (Blueprint $table) {
            $table->index(
                ['clinic_id', 'model_type', 'model_id', 'created_at'],
                'cal_subject_time_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('clinic_activity_logs', function (Blueprint $table) {
            $table->dropIndex('cal_subject_time_idx');
        });
    }
};
