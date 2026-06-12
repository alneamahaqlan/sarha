<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot linking doctors to the services they provide. A service can be
 * offered by several doctors and a doctor performs several services, so a
 * many-to-many table is the right shape. The clinic picks the doctors when
 * adding/editing a service; the same links surface on each doctor's public
 * profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_id', 'doctor_id']);
            $table->index(['doctor_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_service');
    }
};
