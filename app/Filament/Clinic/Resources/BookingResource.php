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
    protected static ?int $navigationSort = 1;

    private static function bookingStatusOptions(): array
    {
        return [
            'new'             => __('admin.status.new'),
            'contacted'       => __('admin.status.contacted'),
            'appointment_set' => __('admin.status.appointment_set'),
            'completed'       => __('admin.status.completed'),
            'no_show'         => __('admin.status.no_show'),
            'cancelled'       => __('admin.status.cancelled'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('clinic_id', auth('clinic')->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('customer_name')->label(__('admin.fields.customer_name'))->disabled(),
            Forms\Components\TextInput::make('customer_phone')->label(__('admin.fields.phone'))->disabled(),
            Forms\Components\Select::make('status')
                ->label(__('admin.fields.status'))
                ->options(self::bookingStatusOptions())->required(),
            Forms\Components\DateTimePicker::make('appointment_at')->label(__('admin.fields.appointment_at')),
            Forms\Components\Textarea::make('clinic_notes')->label(__('admin.fields.notes_clinic'))->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_code')
                    ->label(__('admin.fields.reference_code'))
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('customer_name')->label(__('admin.fields.customer_name'))->searchable(),
                Tables\Columns\TextColumn::make('customer_phone')
                    ->label(__('admin.fields.phone'))
                    ->url(fn($record) => 'tel:' . $record->customer_phone)
                    ->icon('heroicon-o-phone')
                    ->iconColor('success'),
                Tables\Columns\TextColumn::make('service.name')->label(__('admin.fields.service'))->default(__('admin.fields.default_dash')),
                Tables\Columns\TextColumn::make('status')->label(__('admin.fields.status'))->badge()
                    ->color(fn($state) => match($state) {
                        'new' => 'info', 'contacted' => 'warning',
                        'appointment_set' => 'primary', 'completed' => 'success',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn($state) => __('admin.status.' . $state)),
                Tables\Columns\TextColumn::make('appointment_at')->label(__('admin.fields.appointment_at'))->dateTime('Y/m/d H:i'),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.fields.created_at_request'))->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label(__('admin.fields.status'))
                    ->options(self::bookingStatusOptions()),
            ])
            ->actions([\Filament\Actions\EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClinicBookings::route('/'),
            'edit'  => Pages\EditClinicBooking::route('/{record}/edit'),
        ];
    }
}
