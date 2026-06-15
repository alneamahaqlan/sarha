<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer → clinic "follow" relationship (Instagram-style). Distinct from
 * `favorites`: favoriting is a private bookmark, following is a public
 * subscription that surfaces the clinic's new offers on the customer's
 * homepage and bumps the clinic's public follower count.
 *
 * Keyed by (user_id, clinic_id) — one follow per customer per complex.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'clinic_id']);
            // Reverse lookup for "this clinic's followers" / counts.
            $table->index('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_follows');
    }
};
