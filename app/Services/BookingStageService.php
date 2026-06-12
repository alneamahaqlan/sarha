<?php

namespace App\Services;

use App\Models\ClinicBookingStage;
use Illuminate\Support\Collection;

/**
 * Owns the per-clinic Kanban stage list: lazy default seeding and the
 * "primary stage per kind" resolution the board relies on.
 */
class BookingStageService
{
    /**
     * The clinic's stages, ordered. Seeds the default 4-column set the
     * first time a clinic touches the board so existing clinics keep an
     * unchanged layout with zero migration.
     *
     * @return Collection<int, ClinicBookingStage>
     */
    public function ensureDefaults(int $clinicId): Collection
    {
        $stages = ClinicBookingStage::where('clinic_id', $clinicId)
            ->orderBy('sort_order')->orderBy('id')->get();

        if ($stages->isNotEmpty()) {
            return $stages;
        }

        foreach (ClinicBookingStage::DEFAULTS as $i => [$name, $kind, $color]) {
            ClinicBookingStage::create([
                'clinic_id'  => $clinicId,
                'name'       => $name,
                'kind'       => $kind,
                'color'      => $color,
                'sort_order' => $i,
                'is_default' => true,
            ]);
        }

        return ClinicBookingStage::where('clinic_id', $clinicId)
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Map of kind => primary stage id (the first stage of each kind in
     * board order). The primary stage absorbs un-staged bookings of that
     * kind. See Booking::scopeForStage().
     *
     * @param  Collection<int, ClinicBookingStage>  $stages
     * @return array<string, int>
     */
    public function primaryStageIds(Collection $stages): array
    {
        $primary = [];
        foreach ($stages as $stage) {
            if (! isset($primary[$stage->kind])) {
                $primary[$stage->kind] = $stage->id;
            }
        }
        return $primary;
    }
}
