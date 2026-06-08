<?php

use App\Models\ClinicBookingStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Standardise the 4 base stage names to the fixed, non-editable set
     * (جديد / حجز / تمّت الخدمة / ملغي). Base stages label the 4 scopes
     * for the whole board, so every clinic uses the same wording.
     * Only is_default rows are touched; clinic-added stages keep their
     * custom names.
     */
    public function up(): void
    {
        foreach (ClinicBookingStage::BASE_NAMES as $kind => $name) {
            DB::table('clinic_booking_stages')
                ->where('is_default', true)
                ->where('kind', $kind)
                ->update(['name' => $name]);
        }
    }

    public function down(): void
    {
        // No-op: the previous names were per-clinic and not recoverable.
    }
};
