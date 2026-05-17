<?php

namespace App\Filament\Resources\ComplaintResource\Pages;

use App\Filament\Resources\ComplaintResource;
use App\Models\Complaint;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListComplaints extends ListRecords
{
    protected static string $resource = ComplaintResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('admin.tabs.all'))
                ->badge(Complaint::count()),
            'new' => Tab::make(__('admin.complaint_status.new'))
                ->modifyQueryUsing(fn(Builder $q) => $q->where('status', 'new'))
                ->badge(Complaint::where('status', 'new')->count())
                ->badgeColor('info'),
            'in_review' => Tab::make(__('admin.complaint_status.in_review'))
                ->modifyQueryUsing(fn(Builder $q) => $q->where('status', 'in_review'))
                ->badge(Complaint::where('status', 'in_review')->count())
                ->badgeColor('warning'),
            'resolved' => Tab::make(__('admin.complaint_status.resolved'))
                ->modifyQueryUsing(fn(Builder $q) => $q->where('status', 'resolved'))
                ->badgeColor('success'),
            'rejected' => Tab::make(__('admin.complaint_status.rejected'))
                ->modifyQueryUsing(fn(Builder $q) => $q->where('status', 'rejected'))
                ->badgeColor('danger'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
