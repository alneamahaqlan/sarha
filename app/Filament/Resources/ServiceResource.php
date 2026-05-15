<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'service';
    protected static ?string $model = Service::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';
    protected static string|\UnitEnum|null $navigationGroup = 'المحتوى والخدمات';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('clinic_id')
                ->label(__('admin.fields.name_clinic'))->relationship('clinic', 'name')->searchable()->required(),
            Forms\Components\TextInput::make('name')->label(__('admin.fields.name_service'))->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->label(__('admin.fields.description'))->rows(2),
            Forms\Components\TextInput::make('price')
                ->label(__('admin.fields.price_sar'))->required()->numeric()->minValue(0)->live(onBlur: true),
            Forms\Components\TextInput::make('old_price')
                ->label(__('admin.fields.old_price_sar'))->numeric()->minValue(0)->live(onBlur: true)
                ->rules([
                    fn (\Filament\Schemas\Components\Utilities\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                        if ($value !== null && $value !== '' && (float) $value <= (float) $get('price')) {
                            $fail(__('admin.validation.old_price_higher'));
                        }
                    },
                ]),
            Forms\Components\DateTimePicker::make('offer_expires_at')
                ->label(__('admin.fields.offer_expires_at'))
                ->minDate(now()->addDay())
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => filled($get('old_price'))),
            Forms\Components\Toggle::make('is_active')->label(__('admin.fields.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clinic.name')->label(__('admin.fields.name_clinic'))->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->label(__('admin.fields.name_service'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('price')->label(__('admin.fields.price'))->suffix(' ' . __('site.currency_sar'))->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label(__('admin.fields.is_active'))->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.fields.created_at_add'))->date('Y/m/d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('clinic_id')->label(__('admin.fields.name_clinic'))->relationship('clinic', 'name'),
            ])
            ->actions([\Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'edit'  => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
