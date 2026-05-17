<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'category';
    protected static ?string $model = Category::class;

    public static function canDelete($record): bool
    {
        return $record->clinics()->count() === 0;
    }
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static string|\UnitEnum|null $navigationGroup = 'إعدادات النظام';
    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')
                ->label(__('admin.fields.name_ar'))->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn($state, Forms\Set $set) => $set('slug', Str::slug($state))),
            Forms\Components\TextInput::make('name_en')->label(__('admin.fields.name_en')),
            Forms\Components\TextInput::make('slug')->label(__('admin.fields.slug_short'))->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('emoji')->label(__('admin.fields.emoji'))->maxLength(5),
            Forms\Components\TextInput::make('icon')->label(__('admin.fields.icon')),
            Forms\Components\Toggle::make('is_active')->label(__('admin.fields.is_active'))->default(true),
            Forms\Components\TextInput::make('sort_order')->label(__('admin.fields.sort_order_short'))->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('emoji')->label(''),
                Tables\Columns\TextColumn::make('name')->label(__('admin.fields.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label(__('admin.fields.slug_short')),
                Tables\Columns\TextColumn::make('clinics_count')->label(__('admin.fields.clinics_count'))->counts('clinics'),
                Tables\Columns\IconColumn::make('is_active')->label(__('admin.fields.is_active'))->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label(__('admin.fields.sort_order_short'))->sortable(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCategories::route('/'),
        ];
    }
}
