<?php

namespace App\Filament\Clinic\Resources;

use App\Filament\Clinic\Resources\BookingResource\Pages;
use App\Filament\Concerns\HasTranslatableLabels;
use App\Models\Booking;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BookingResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'booking';
    protected static ?string $model = Booking::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';
    protected static string|\UnitEnum|null $navigationGroup = 'الحجوزات والعملاء';
    protected static ?string $navigationLabel = 'طلبات الحجز';
    protected static ?string $modelLabel = 'طلب حجز';
    protected static ?string $pluralModelLabel = 'طلبات الحجز';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('clinic_id', auth('clinic')->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('customer_name')->label('اسم العميل')->disabled(),
            Forms\Components\TextInput::make('customer_phone')->label('رقم الهاتف')->disabled(),
            Forms\Components\Select::make('status')
                ->label('الحالة')
                ->options([
                    'new' => 'جديد', 'contacted' => 'تم التواصل',
                    'appointment_set' => 'تم تحديد موعد', 'completed' => 'مكتمل',
                    'no_show' => 'لم يحضر', 'cancelled' => 'ملغي',
                ])->required(),
            Forms\Components\DateTimePicker::make('appointment_at')->label('موعد'),
            Forms\Components\Textarea::make('clinic_notes')->label('ملاحظات المجمع')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')->label('العميل')->searchable(),
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
                Tables\Columns\TextColumn::make('appointment_at')->label('الموعد')->dateTime('Y/m/d H:i'),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الطلب')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['new' => 'جديد', 'contacted' => 'تم التواصل', 'appointment_set' => 'تم تحديد موعد', 'completed' => 'مكتمل', 'no_show' => 'لم يحضر', 'cancelled' => 'ملغي']),
            ])
            ->actions([\Filament\Actions\EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClinicBookings::route('/'),
            'edit' => Pages\EditClinicBooking::route('/{record}/edit'),
        ];
    }
}
