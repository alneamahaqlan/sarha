<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('search_appearances')->default(0);
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('bookings_count')->default(0);
            $table->unsignedInteger('quote_requests_count')->default(0);
            $table->timestamps();

            $table->unique(['clinic_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_stats');
    }
};
