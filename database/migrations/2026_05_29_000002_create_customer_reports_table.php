<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer-side platform reports — the user→admin counterpart to
 * clinic_reports. Used for: bug reports, UX suggestions, abuse reports
 * about a clinic the customer interacted with, inappropriate content,
 * or anything else that doesn't fit the customer-complaint flow
 * (complaints stay scoped to "this clinic did X to me").
 *
 * Kept on its own table so admin can triage end-user reports without
 * mixing them with clinic-side reports or with customer complaints
 * about specific clinic interactions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Optional — when the report concerns a specific complex
            // (e.g. abuse, inappropriate content, fraud). Null for
            // generic platform reports (bug, suggestion).
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();

            // bug              — technical/platform bug
            // suggestion       — UX or feature improvement
            // clinic_concern   — a general concern about a complex that
            //                    isn't tied to a specific booking/service
            // inappropriate    — wrong/offensive content shown on platform
            // other
            $table->string('type');
            $table->string('priority')->default('medium');     // low | medium | high
            $table->string('status')->default('new');          // new | in_review | resolved | rejected

            $table->string('subject');
            $table->text('description');

            $table->text('admin_notes')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_reports');
    }
};
