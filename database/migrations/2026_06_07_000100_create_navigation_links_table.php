<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed header/footer navigation links. A link can target a static
 * page, a named route, or an arbitrary URL. Footer links are grouped into
 * columns (1-3). Managed from /app/admin/navigation-links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_links', function (Blueprint $table) {
            $table->id();
            $table->enum('location', ['header', 'footer'])->index();
            $table->unsignedTinyInteger('footer_column')->nullable();
            $table->string('label_ar');
            $table->string('label_en')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('static_page_id')->nullable()->constrained('static_pages')->nullOnDelete();
            $table->string('route_name')->nullable();
            $table->boolean('open_new_tab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_links');
    }
};
