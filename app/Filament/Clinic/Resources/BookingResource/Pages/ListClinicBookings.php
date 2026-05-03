<?php

namespace App\Filament\Clinic\Resources\BookingResource\Pages;

use App\Filament\Clinic\Resources\BookingResource;
use Filament\Resources\Pages\ListRecords;

class ListClinicBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;
}
