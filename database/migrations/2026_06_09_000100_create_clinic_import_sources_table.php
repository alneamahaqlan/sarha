<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A saved "external data source" — a public Google Sheet a clinic
     * imports leads/customers from. Persisted so a sheet tied to a running
     * paid campaign can be re-pulled later with the same column mapping and
     * defaults, without re-typing everything. See clinic_import_runs for the
     * per-pull history (date + row range) that powers de-duplication.
     */
    public function up(): void
    {
        Schema::create('clinic_import_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');                 // friendly label e.g. "حملة سناب يناير"
            $table->text('sheet_url');              // public Google Sheet URL
            // Column letters keyed by our field: {"customer_name":"B","customer_phone":"C",...}
            $table->json('column_map');
            // Unified per-import values applied to every row:
            // {"status":"new","acquisition_source":"snapchat","assignee_type":..,"assignee_id":..}
            $table->json('defaults')->nullable();
            // Remembered service-name resolutions so the clinic isn't asked
            // twice about the same alias: {"زراعه اسنان":{"action":"map","service_id":12}}
            $table->json('service_aliases')->nullable();
            $table->timestamp('last_imported_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_import_sources');
    }
};
