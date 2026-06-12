<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordered, toggleable content blocks for a landing page — the backing store
 * for the drag-and-drop builder. Each row's id is a stable sortable key for
 * dnd-kit and the reorder endpoint. `config` holds the per-block-type shape
 * (heading, source mode, manual ids, copy, countdown target, faq pairs, …).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index(); // hero|services|offers|doctors|gallery|reviews|faq|map|booking|countdown
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('block_version')->default(1);
            $table->timestamps();

            $table->index(['landing_page_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_blocks');
    }
};
