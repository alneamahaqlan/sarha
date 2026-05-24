<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One reply per clinic per request; clinic chooses public vs private.
        Schema::create('price_quote_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_quote_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->unique(['price_quote_request_id', 'clinic_id'], 'pqr_reply_unique');
            $table->index(['clinic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_quote_replies');
    }
};
