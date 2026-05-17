<?php

namespace App\Filament\Clinic\Resources;

use App\Filament\Clinic\Resources\ArticleResource\Pages;
use App\Filament\Concerns\HasTranslatableLabels;
use App\Models\Article;
use App\Services\AnthropicService;
use Filament\Actions\Action as FormAction;
use Filament\Forms;
use Filament\Notifications\Notification;
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
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('clinic_id', auth('clinic')->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('title')
                ->label(__('admin.fields.title_article'))->required()->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn($state, Forms\Set $set) => $set('slug', Str::slug($state))),
            Forms\Components\TextInput::make('slug')->label(__('admin.fields.slug_short'))->required()->unique(ignoreRecord: true),
            Forms\Components\Textarea::make('excerpt')
                ->label(__('admin.fields.excerpt'))
                ->rows(2)->maxLength(300)
                ->hintAction(
                    FormAction::make('ai_excerpt')
                        ->label(__('admin.actions.generate_excerpt_ai'))
                        ->icon('heroicon-o-sparkles')
                        ->color('primary')
                        ->action(function (Forms\Set $set, Forms\Get $get) {
                            self::runAi('excerpt', $get, $set);
                        })
                ),
            Forms\Components\RichEditor::make('body')
                ->label(__('admin.fields.body'))
                ->required()->columnSpanFull()
                ->hintAction(
                    FormAction::make('ai_article')
                        ->label(__('admin.actions.generate_article_ai'))
                        ->icon('heroicon-o-sparkles')
                        ->color('primary')
                        ->action(function (Forms\Set $set, Forms\Get $get) {
                            self::runAi('article', $get, $set);
                        })
                ),
            Forms\Components\FileUpload::make('cover_image')->label(__('admin.fields.cover_image'))->image()->directory('articles'),
            Forms\Components\Toggle::make('is_published')->label(__('admin.fields.is_published'))->default(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label(__('admin.fields.title'))->searchable()->sortable(),
                Tables\Columns\IconColumn::make('is_published')->label(__('admin.fields.is_published'))->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.fields.created_at'))->date('Y/m/d')->sortable(),
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
            'index'  => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit'   => Pages\EditArticle::route('/{record}/edit'),
        ];
    }

    private static function runAi(string $kind, Forms\Get $get, Forms\Set $set): void
    {
        $title = $get('title');
        if (! $title) {
            Notification::make()->title(__('admin.ai.title_first'))->warning()->send();
            return;
        }

        $clinic = auth('clinic')->user();
        $service = app(AnthropicService::class);

        if (! $service->isConfigured()) {
            Notification::make()->title(__('admin.ai.not_configured'))->danger()->send();
            return;
        }

        try {
            $result = $kind === 'excerpt'
                ? $service->generateExcerpt($title, $clinic->name ?? '')
                : $service->generateArticle($title, $clinic->name ?? '');

            $set($kind === 'excerpt' ? 'excerpt' : 'body', $result);
            Notification::make()->title(__('admin.ai.generated'))->success()->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('admin.ai.failed'))
                ->body($e->getMessage())
                ->danger()->send();
        }
    }
}
