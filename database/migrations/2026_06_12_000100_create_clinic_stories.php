<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            // Relative storage path under the "stories" directory.
            $table->string('image');
            $table->string('caption')->nullable();
            // Optional auto-expiry (Instagram defaults to 24h; null = stays
            // until the clinic deactivates/removes it).
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'is_active'], 'clinic_stories_active_idx');
            $table->index(['clinic_id', 'sort_order'], 'clinic_stories_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_stories');
    }
};
