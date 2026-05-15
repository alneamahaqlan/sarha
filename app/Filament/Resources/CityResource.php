<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\CityResource\Pages;
use App\Models\City;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CityResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'city';
    protected static ?string $model = City::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';
    protected static string|\UnitEnum|null $navigationGroup = 'إعدادات النظام';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->label(__('admin.fields.name_city_ar'))->required(),
            Forms\Components\TextInput::make('name_en')->label(__('admin.fields.name_city_en')),
            Forms\Components\Toggle::make('is_active')->label(__('admin.fields.is_active'))->default(true),
            Forms\Components\TextInput::make('sort_order')->label(__('admin.fields.sort_order'))->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('admin.fields.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name_en')->label(__('admin.fields.name_en')),
                Tables\Columns\TextColumn::make('clinics_count')->label(__('admin.fields.clinics_count'))->counts('clinics'),
                Tables\Columns\IconColumn::make('is_active')->label(__('admin.fields.is_active'))->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label(__('admin.fields.sort_order_short'))->sortable(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCities::route('/'),
        ];
    }
}
