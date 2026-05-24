<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            // The clinic (department) the doctor works in — optional.
            $table->foreignId('sub_clinic_id')->nullable()->after('clinic_id')
                ->constrained('sub_clinics')->nullOnDelete();
            $table->string('gender')->nullable()->after('specialty');        // male | female
            $table->string('university')->nullable()->after('years_experience');
            $table->string('languages')->nullable()->after('university');
            $table->text('qualifications')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropForeign(['sub_clinic_id']);
            $table->dropColumn(['sub_clinic_id', 'gender', 'university', 'languages', 'qualifications']);
        });
    }
};
