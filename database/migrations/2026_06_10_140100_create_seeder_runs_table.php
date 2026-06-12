<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks Seeder-Center "reseed" operations (re-running a demo batch after
     * a permanent purge). Light async-job status record + result counts so the
     * React page can poll progress for the heavy seeders that run on the queue.
     */
    public function up(): void
    {
        Schema::create('seeder_runs', function (Blueprint $table) {
            $table->id();
            $table->string('batch', 40)->index();
            // queued | running | done | failed
            $table->string('status', 16)->default('queued');
            $table->text('message')->nullable();
            $table->unsignedInteger('rows_created')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seeder_runs');
    }
};
