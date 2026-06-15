<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Badges Center — admin-managed achievement/trust badges shown on the public
 * clinic header and cards. Two assignment modes:
 *   - manual: admin attaches the badge to specific complexes.
 *   - auto:   the badge is bound to a rule in BadgeRuleRegistry (e.g.
 *             "most booked", "fastest growing this week") and the
 *             `badges:recompute` command stamps the winners here.
 *
 * Adding a NEW automatic badge type later = one entry in BadgeRuleRegistry;
 * everything else (label, icon, colour, activation) is managed from the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();              // stable slug
            $table->string('label_ar');
            $table->string('label_en');
            $table->string('icon')->default('star-solid'); // shared icon key (Blade x-icon + React)
            $table->string('color')->default('gold');       // palette key (shared Blade + React)
            $table->string('placement')->default('both');   // header | cards | both
            $table->string('mode')->default('manual');       // manual | auto
            $table->string('rule_key')->nullable();           // BadgeRuleRegistry key (auto mode)
            $table->json('rule_params')->nullable();          // {window_days, top_n, min, ...}
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('badge_clinic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('manual');     // manual | auto
            $table->timestamp('expires_at')->nullable();      // for time-windowed auto badges
            $table->timestamps();

            $table->unique(['badge_id', 'clinic_id']);
            $table->index(['clinic_id', 'badge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_clinic');
        Schema::dropIfExists('badges');
    }
};
