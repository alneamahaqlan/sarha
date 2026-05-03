<?php

namespace App\Filament\Clinic\Resources\ServiceResource\Pages;

use App\Filament\Clinic\Resources\ServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageServices extends ManageRecords
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->mutateFormDataUsing(function (array $data) {
            $data['clinic_id'] = auth('clinic')->id();
            return $data;
        })];
    }
}
