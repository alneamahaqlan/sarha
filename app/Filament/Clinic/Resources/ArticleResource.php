<?php

namespace App\Filament\Clinic\Resources;

use App\Filament\Clinic\Resources\ArticleResource\Pages;
use App\Filament\Concerns\HasTranslatableLabels;
use App\Models\Article;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ArticleResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'my_article';
    protected static ?string $model = Article::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|\UnitEnum|null $navigationGroup = 'المحتوى والمقالات';
    protected static ?string $navigationLabel = 'مقالاتي';
    protected static ?string $modelLabel = 'مقال';
    protected static ?string $pluralModelLabel = 'المقالات';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('clinic_id', auth('clinic')->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('title')
                ->label('عنوان المقال')->required()->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn($state, Forms\Set $set) => $set('slug', Str::slug($state))),
            Forms\Components\TextInput::make('slug')->label('الرابط')->required()->unique(ignoreRecord: true),
            Forms\Components\Textarea::make('excerpt')->label('مقتطف')->rows(2)->maxLength(300),
            Forms\Components\RichEditor::make('body')->label('محتوى المقال')->required()->columnSpanFull(),
            Forms\Components\FileUpload::make('cover_image')->label('صورة المقال')->image()->directory('articles'),
            Forms\Components\Toggle::make('is_published')->label('منشور')->default(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('العنوان')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('is_published')->label('منشور')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الإنشاء')->date('Y/m/d')->sortable(),
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
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
