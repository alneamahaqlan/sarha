<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BookingResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'booking';
    protected static ?string $model = Booking::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';
    protected static string|\UnitEnum|null $navigationGroup = 'إدارة العيادات';
    protected static ?string $navigationLabel = 'طلبات الحجز';
    protected static ?string $modelLabel = 'طلب حجز';
    protected static ?string $pluralModelLabel = 'طلبات الحجز';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('clinic_id')
                ->label('العيادة')->relationship('clinic', 'name')->searchable()->required(),
            Forms\Components\TextInput::make('customer_name')->label('اسم العميل')->required(),
            Forms\Components\TextInput::make('customer_phone')->label('رقم الهاتف')->required()->tel(),
            Forms\Components\Select::make('service_id')
                ->label('الخدمة')->relationship('service', 'name')->searchable(),
            Forms\Components\Select::make('status')
                ->label('الحالة')
                ->options([
                    'new' => 'جديد',
                    'contacted' => 'تم التواصل',
                    'appointment_set' => 'تم تحديد موعد',
                    'completed' => 'مكتمل',
                    'no_show' => 'لم يحضر',
                    'cancelled' => 'ملغي',
                ])->required(),
            Forms\Components\DateTimePicker::make('appointment_at')->label('موعد'),
            Forms\Components\Textarea::make('notes')->label('ملاحظات العميل')->rows(3),
            Forms\Components\Textarea::make('clinic_notes')->label('ملاحظات العيادة')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clinic.name')->label('العيادة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer_name')->label('العميل')->searchable(),
                Tables\Columns\TextColumn::make('customer_phone')->label('الهاتف'),
                Tables\Columns\TextColumn::make('service.name')->label('الخدمة'),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')->badge()
                    ->color(fn($state) => match($state) {
                        'new' => 'info',
                        'contacted' => 'warning',
                        'appointment_set' => 'primary',
                        'completed' => 'success',
                        'no_show', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'new' => 'جديد',
                        'contacted' => 'تم التواصل',
                        'appointment_set' => 'تم تحديد موعد',
                        'completed' => 'مكتمل',
                        'no_show' => 'لم يحضر',
                        'cancelled' => 'ملغي',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الطلب')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['new' => 'جديد', 'contacted' => 'تم التواصل', 'appointment_set' => 'تم تحديد موعد', 'completed' => 'مكتمل', 'no_show' => 'لم يحضر', 'cancelled' => 'ملغي']),
                Tables\Filters\SelectFilter::make('clinic_id')->label('العيادة')->relationship('clinic', 'name'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
