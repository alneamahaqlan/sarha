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
    protected static ?string $heading = 'آخر طلبات الحجز';

    public function table(Table $table): Table
    {
        return $table
            ->query(Booking::latest()->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('clinic.name')->label('العيادة')->searchable(),
                Tables\Columns\TextColumn::make('customer_name')->label('العميل'),
                Tables\Columns\TextColumn::make('customer_phone')->label('الهاتف'),
                Tables\Columns\TextColumn::make('service.name')->label('الخدمة')->default('—'),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->color(fn($state) => match($state) {
                        'new' => 'info', 'contacted' => 'warning',
                        'appointment_set' => 'primary', 'completed' => 'success',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'new' => 'جديد', 'contacted' => 'تم التواصل',
                        'appointment_set' => 'تم تحديد موعد', 'completed' => 'مكتمل',
                        'no_show' => 'لم يحضر', 'cancelled' => 'ملغي', default => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->since(),
            ])
            ->paginated(false);
    }
}
