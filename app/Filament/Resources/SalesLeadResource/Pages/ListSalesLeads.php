<?php

namespace App\Filament\Resources\SalesLeadResource\Pages;

use App\Filament\Resources\SalesLeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesLeads extends ListRecords
{
    protected static string $resource = SalesLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label(__('admin.actions.create') . ' ' . __('admin.res_sales_lead'))];
    }
}
