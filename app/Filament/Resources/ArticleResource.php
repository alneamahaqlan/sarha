<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use App\Models\Clinic;
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
    protected static ?string $navigationLabel = 'المقالات';
    protected static ?string $modelLabel = 'مقال';
    protected static ?string $pluralModelLabel = 'المقالات';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('clinic_id')
                ->label('المجمع')->relationship('clinic', 'name')->searchable()->required(),
            Forms\Components\TextInput::make('title')->label('العنوان')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')->label('الرابط')->unique(ignoreRecord: true),
            Forms\Components\Textarea::make('excerpt')->label('مقتطف')->rows(2),
            Forms\Components\Toggle::make('is_published')->label('منشور')->default(false),
            Forms\Components\Toggle::make('ai_generated')->label('محتوى ذكاء اصطناعي'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('العنوان')->searchable()->sortable()->limit(50),
                Tables\Columns\TextColumn::make('clinic.name')->label('المجمع')->sortable(),
                Tables\Columns\IconColumn::make('is_published')->label('منشور')->boolean(),
                Tables\Columns\IconColumn::make('ai_generated')->label('AI')->boolean(),
                Tables\Columns\TextColumn::make('views_count')->label('المشاهدات')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الإنشاء')->date('Y/m/d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('clinic_id')->label('المجمع')->relationship('clinic', 'name'),
                Tables\Filters\TernaryFilter::make('is_published')->label('الحالة')->trueLabel('منشور')->falseLabel('مسودة'),
            ])
            ->actions([\Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
