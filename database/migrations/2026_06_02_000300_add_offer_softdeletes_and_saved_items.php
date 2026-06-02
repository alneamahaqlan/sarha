<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two related changes for the "save services & offers" feature:
     *  1. Soft-deletes on offers so a customer's saved offer survives a
     *     delete and can still be shown (labelled "محذوف").
     *  2. A polymorphic `saved_items` table for per-user saved services
     *     and offers (clinic favourites keep their own `favorites` table).
     */
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::create('saved_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('favoritable'); // favoritable_type + favoritable_id (+ index)
            $table->timestamps();
            $table->unique(['user_id', 'favoritable_type', 'favoritable_id'], 'saved_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_items');

        Schema::table('offers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
