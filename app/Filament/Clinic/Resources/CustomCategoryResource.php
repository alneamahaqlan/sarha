<?php

namespace App\Filament\Clinic\Resources;

use App\Filament\Clinic\Resources\CustomCategoryResource\Pages;
use App\Filament\Concerns\HasTranslatableLabels;
use App\Models\CustomCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CustomCategoryResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'custom_category';
    protected static ?string $model = CustomCategory::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static string|\UnitEnum|null $navigationGroup = 'إدارة خدماتي';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('clinic_id', auth('clinic')->id());
    }

    public static function canDelete($record): bool
    {
        return $record->services()->count() === 0;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('emoji')
                ->label(__('admin.fields.emoji'))
                ->default('🩺')
                ->maxLength(5),
            Forms\Components\TextInput::make('name')
                ->label(__('admin.fields.name'))
                ->required()
                ->maxLength(255),
            Forms\Components\Toggle::make('is_active')
                ->label(__('admin.fields.is_active'))
                ->default(true),
            Forms\Components\TextInput::make('sort_order')
                ->label(__('admin.fields.sort_order'))
                ->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('emoji')->label('')->size('lg'),
                Tables\Columns\TextColumn::make('name')->label(__('admin.fields.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('services_count')
                    ->label(__('admin.fields.services_count'))
                    ->counts('services')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')->label(__('admin.fields.is_active'))->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label(__('admin.fields.sort_order'))->sortable(),
            ])
            ->reorderable('sort_order')
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCustomCategories::route('/'),
        ];
    }
}
