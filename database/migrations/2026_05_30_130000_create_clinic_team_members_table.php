<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Phone is per-tenant unique (clinic scope) — cross-clinic
            // uniqueness is enforced at the request layer because
            // making it global-unique would also collide with clinics.phone.
            $table->string('phone', 32);
            $table->string('password');
            // Three fixed roles only (per spec — no custom roles).
            // Stored as string to keep migrations cheap; the enum class
            // is the source of truth.
            $table->string('role', 16);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // One phone per clinic; soft-deleted rows do NOT count against
            // the unique constraint because Laravel filters them in queries,
            // but MySQL doesn't — so we don't add a unique index here and
            // rely on the request validation to scope by deleted_at IS NULL.
            $table->index(['clinic_id', 'is_active']);
            $table->index(['phone', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_team_members');
    }
};
