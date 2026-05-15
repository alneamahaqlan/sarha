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
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static string|\UnitEnum|null $navigationGroup = 'إعدادات النظام';
    protected static ?string $navigationLabel = 'التخصصات';
    protected static ?string $modelLabel = 'تخصص';
    protected static ?string $pluralModelLabel = 'التخصصات';
    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')
                ->label('الاسم (عربي)')->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn($state, Forms\Set $set) => $set('slug', Str::slug($state))),
            Forms\Components\TextInput::make('name_en')->label('الاسم (إنجليزي)'),
            Forms\Components\TextInput::make('slug')->label('الرابط')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('emoji')->label('الإيموجي')->maxLength(5),
            Forms\Components\TextInput::make('icon')->label('الأيقونة'),
            Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
            Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('emoji')->label(''),
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('الرابط'),
                Tables\Columns\TextColumn::make('clinics_count')->label('عدد المجمعات')->counts('clinics'),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('الترتيب')->sortable(),
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
