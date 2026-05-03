<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_leads', function (Blueprint $table) {
            $table->id();
            $table->string('clinic_name');
            $table->string('contact_name')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['new', 'contacted', 'interested', 'negotiating', 'converted', 'lost'])->default('new');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_leads');
    }
};
