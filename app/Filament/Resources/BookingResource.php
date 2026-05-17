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
    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المجمعات';
    protected static ?int $navigationSort = 3;

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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('clinic_id')
                ->label(__('admin.fields.name_clinic'))->relationship('clinic', 'name')->searchable()->required(),
            Forms\Components\TextInput::make('customer_name')->label(__('admin.fields.customer_name'))->required(),
            Forms\Components\TextInput::make('customer_phone')->label(__('admin.fields.phone'))->required()->tel(),
            Forms\Components\Select::make('service_id')
                ->label(__('admin.fields.service'))->relationship('service', 'name')->searchable(),
            Forms\Components\Select::make('status')
                ->label(__('admin.fields.status'))
                ->options(self::bookingStatusOptions())->required(),
            Forms\Components\DateTimePicker::make('appointment_at')->label(__('admin.fields.appointment_at')),
            Forms\Components\Textarea::make('notes')->label(__('admin.fields.notes_customer'))->rows(3),
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
                Tables\Columns\TextColumn::make('clinic.name')->label(__('admin.fields.name_clinic'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer_name')->label(__('admin.fields.customer_name'))->searchable(),
                Tables\Columns\TextColumn::make('customer_phone')->label(__('admin.fields.phone')),
                Tables\Columns\TextColumn::make('service.name')->label(__('admin.fields.service'))->default(__('admin.fields.default_dash')),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.fields.status'))->badge()
                    ->color(fn($state) => match($state) {
                        'new' => 'info',
                        'contacted' => 'warning',
                        'appointment_set' => 'primary',
                        'completed' => 'success',
                        'no_show', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => __('admin.status.' . $state, [], app()->getLocale()) ?: $state),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.fields.created_at_request'))->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label(__('admin.fields.status'))
                    ->options(self::bookingStatusOptions()),
                Tables\Filters\SelectFilter::make('clinic_id')->label(__('admin.fields.name_clinic'))->relationship('clinic', 'name'),
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
            'edit'  => Pages\EditBooking::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
