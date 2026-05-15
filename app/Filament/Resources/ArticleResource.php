<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ArticleResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'article';
    protected static ?string $model = Article::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|\UnitEnum|null $navigationGroup = 'المحتوى والخدمات';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('clinic_id')
                ->label(__('admin.fields.name_clinic'))->relationship('clinic', 'name')->searchable()->required(),
            Forms\Components\TextInput::make('title')->label(__('admin.fields.title'))->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->label(__('admin.fields.slug_short'))->unique(ignoreRecord: true),
            Forms\Components\Textarea::make('excerpt')->label(__('admin.fields.excerpt'))->rows(2),
            Forms\Components\Toggle::make('is_published')->label(__('admin.fields.is_published'))->default(false),
            Forms\Components\Toggle::make('ai_generated')->label(__('admin.fields.ai_generated')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label(__('admin.fields.title'))->searchable()->sortable()->limit(50),
                Tables\Columns\TextColumn::make('clinic.name')->label(__('admin.fields.name_clinic'))->sortable(),
                Tables\Columns\IconColumn::make('is_published')->label(__('admin.fields.is_published'))->boolean(),
                Tables\Columns\IconColumn::make('ai_generated')->label(__('admin.fields.ai_short'))->boolean(),
                Tables\Columns\TextColumn::make('views_count')->label(__('admin.fields.views_count'))->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.fields.created_at'))->date('Y/m/d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('clinic_id')->label(__('admin.fields.name_clinic'))->relationship('clinic', 'name'),
                Tables\Filters\TernaryFilter::make('is_published')->label(__('admin.fields.status'))
                    ->trueLabel(__('admin.fields.is_published'))
                    ->falseLabel(__('admin.status.pending')),
            ])
            ->actions([\Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'edit'  => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
