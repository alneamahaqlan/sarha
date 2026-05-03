<?php

namespace App\Filament\Clinic\Resources\ArticleResource\Pages;

use App\Filament\Clinic\Resources\ArticleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['clinic_id'] = auth('clinic')->id();
        return $data;
    }
}
