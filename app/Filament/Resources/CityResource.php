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
    protected static ?string $navigationLabel = 'المدن';
    protected static ?string $modelLabel = 'مدينة';
    protected static ?string $pluralModelLabel = 'المدن';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->label('اسم المدينة (عربي)')->required(),
            Forms\Components\TextInput::make('name_en')->label('اسم المدينة (إنجليزي)'),
            Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
            Forms\Components\TextInput::make('sort_order')->label('ترتيب العرض')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name_en')->label('الاسم إنجليزي'),
                Tables\Columns\TextColumn::make('clinics_count')->label('عدد المجمعات')->counts('clinics'),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('الترتيب')->sortable(),
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
