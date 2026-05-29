<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Restrictions + Response Templates.
 *
 * `ai_restrictions` is a typed list of admin-managed rules consulted by
 * the AiAssistantInterceptor. Four types:
 *  - banned_topic       — refuse + canned reply when the query mentions
 *                         the value (e.g. "تشخيص طبي").
 *  - emergency_keyword  — divert to the emergency response (ambulance +
 *                         calm message) when the value appears.
 *  - clinic_blocklist   — strip the clinic from recommendations; `value`
 *                         is the clinic id as a string.
 *  - category_blocklist — same idea, for categories.
 *
 * `ai_response_templates` is a separate library so admins can curate
 * reusable canned replies and pick one per restriction (or just keep them
 * around for documentation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_restrictions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['banned_topic', 'emergency_keyword', 'clinic_blocklist', 'category_blocklist']);
            // The literal — phrase to match (banned_topic / emergency_keyword)
            // or an id-as-string (clinic_blocklist / category_blocklist).
            $table->string('value', 255);
            // Optional canned reply that replaces the assistant's normal
            // output when this rule fires (banned_topic only).
            $table->text('response_override')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            // One value-per-type uniqueness, so the admin can't double-add
            // the same banned topic by accident.
            $table->unique(['type', 'value']);
            $table->index(['type', 'is_active']);
        });

        Schema::create('ai_response_templates', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->text('content');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_response_templates');
        Schema::dropIfExists('ai_restrictions');
    }
};
