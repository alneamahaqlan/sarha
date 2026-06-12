<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A broadcast price-quote request now also targets specializations (categories),
 * so a dermatology clinic never receives a dental request it can't serve. The
 * request reaches only clinics that hold at least one of the chosen categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_quote_request_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_quote_request_id')->constrained('price_quote_requests')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unique(['price_quote_request_id', 'category_id'], 'pqr_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_quote_request_category');
    }
};
