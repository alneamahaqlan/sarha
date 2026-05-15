<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestBookingsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('admin.widgets.latest_bookings');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Booking::latest()->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('clinic.name')->label(__('admin.fields.name_clinic'))->searchable(),
                Tables\Columns\TextColumn::make('customer_name')->label(__('admin.fields.customer_name')),
                Tables\Columns\TextColumn::make('customer_phone')->label(__('admin.fields.phone')),
                Tables\Columns\TextColumn::make('service.name')->label(__('admin.fields.service'))->default(__('admin.fields.default_dash')),
                Tables\Columns\TextColumn::make('status')->label(__('admin.fields.status'))->badge()
                    ->color(fn($state) => match($state) {
                        'new' => 'info', 'contacted' => 'warning',
                        'appointment_set' => 'primary', 'completed' => 'success',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn($state) => __('admin.status.' . $state)),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.fields.date'))->since(),
            ])
            ->paginated(false);
    }
}
