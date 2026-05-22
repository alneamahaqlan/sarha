<?php

namespace App\Filament\Clinic\Resources\CustomCategoryResource\Pages;

use App\Filament\Clinic\Resources\CustomCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomCategories extends ManageRecords
{
    protected static string $resource = CustomCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->mutateFormDataUsing(function (array $data) {
                $data['clinic_id'] = auth('clinic')->id();
                return $data;
            }),
        ];
    }
}
