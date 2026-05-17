<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 16)->unique();
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();

            $table->enum('type', ['quality', 'pricing', 'misleading_info', 'other']);
            $table->enum('status', ['new', 'in_review', 'resolved', 'rejected'])->default('new');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');

            $table->text('subject');
            $table->text('description');
            $table->text('admin_notes')->nullable();
            $table->text('resolution')->nullable();

            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('clinic_notified')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority']);
            $table->index('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
