<?php

namespace App\Filament\Clinic\Resources;

use App\Filament\Clinic\Resources\PriceQuoteRequestResource\Pages;
use App\Filament\Concerns\HasTranslatableLabels;
use App\Models\PriceQuoteRequest;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PriceQuoteRequestResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'price_quote_request';
    protected static ?string $model = PriceQuoteRequest::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';
    protected static string|\UnitEnum|null $navigationGroup = 'الحجوزات والعملاء';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('clinic_id', auth('clinic')->id());
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PriceQuoteRequest::where('clinic_id', auth('clinic')->id())
            ->where('status', 'new')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('customer_name')->label(__('admin.fields.customer_name'))->disabled(),
            Forms\Components\TextInput::make('customer_phone')
                ->label(__('admin.fields.phone'))
                ->disabled()
                ->suffixAction(
                    \Filament\Actions\Action::make('call')
                        ->icon('heroicon-o-phone')
                        ->color('success')
                        ->url(fn($record) => $record?->customer_phone ? 'tel:' . $record->customer_phone : null)
                ),
            Forms\Components\TextInput::make('service_name')->label(__('admin.fields.service_name'))->disabled(),
            Forms\Components\Textarea::make('description')->label(__('admin.fields.description'))->disabled()->rows(3)->columnSpanFull(),
            Forms\Components\Select::make('status')->label(__('admin.fields.status'))
                ->options([
                    'new'     => __('admin.quote_status.new'),
                    'replied' => __('admin.quote_status.replied'),
                    'closed'  => __('admin.quote_status.closed'),
                ])->required(),
            Forms\Components\Textarea::make('clinic_reply')
                ->label(__('admin.fields.clinic_reply'))
                ->rows(4)
                ->columnSpanFull()
                ->required(fn(Forms\Get $get) => $get('status') === 'replied'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')->label(__('admin.fields.customer_name'))->searchable(),
                Tables\Columns\TextColumn::make('customer_phone')
                    ->label(__('admin.fields.phone'))
                    ->url(fn($record) => 'tel:' . $record->customer_phone)
                    ->icon('heroicon-o-phone')
                    ->iconColor('success'),
                Tables\Columns\TextColumn::make('service_name')->label(__('admin.fields.service_name'))->limit(40),
                Tables\Columns\TextColumn::make('status')->label(__('admin.fields.status'))->badge()
                    ->formatStateUsing(fn($state) => __('admin.quote_status.' . $state))
                    ->color(fn($state) => ['new' => 'warning', 'replied' => 'success', 'closed' => 'gray'][$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.fields.created_at'))->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label(__('admin.fields.status'))
                    ->options([
                        'new' => __('admin.quote_status.new'),
                        'replied' => __('admin.quote_status.replied'),
                    ]),
            ])
            ->actions([\Filament\Actions\EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPriceQuoteRequests::route('/'),
            'edit'  => Pages\EditPriceQuoteRequest::route('/{record}/edit'),
        ];
    }
}
