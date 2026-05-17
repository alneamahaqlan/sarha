<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\PriceQuoteRequestResource\Pages;
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
    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المجمعات';
    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        $count = PriceQuoteRequest::where('status', 'new')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('clinic_id')->label(__('admin.fields.name_clinic'))
                ->relationship('clinic', 'name')->searchable()->required(),
            Forms\Components\TextInput::make('customer_name')->label(__('admin.fields.customer_name'))->required(),
            Forms\Components\TextInput::make('customer_phone')->label(__('admin.fields.phone'))->required()->tel(),
            Forms\Components\TextInput::make('service_name')->label(__('admin.fields.service_name'))->required(),
            Forms\Components\Select::make('status')->label(__('admin.fields.status'))
                ->options([
                    'new'     => __('admin.quote_status.new'),
                    'replied' => __('admin.quote_status.replied'),
                    'closed'  => __('admin.quote_status.closed'),
                ])->required(),
            Forms\Components\Textarea::make('description')->label(__('admin.fields.description'))->rows(3)->columnSpanFull(),
            Forms\Components\Textarea::make('clinic_reply')->label(__('admin.fields.clinic_reply'))->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clinic.name')->label(__('admin.fields.name_clinic'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer_name')->label(__('admin.fields.customer_name'))->searchable(),
                Tables\Columns\TextColumn::make('customer_phone')->label(__('admin.fields.phone')),
                Tables\Columns\TextColumn::make('service_name')->label(__('admin.fields.service_name'))->limit(40),
                Tables\Columns\TextColumn::make('status')->label(__('admin.fields.status'))->badge()
                    ->formatStateUsing(fn($state) => __('admin.quote_status.' . $state))
                    ->color(fn($state) => ['new' => 'warning', 'replied' => 'success', 'closed' => 'gray'][$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.fields.created_at'))->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label(__('admin.fields.status'))
                    ->options([
                        'new' => __('admin.quote_status.new'),
                        'replied' => __('admin.quote_status.replied'),
                        'closed' => __('admin.quote_status.closed'),
                    ]),
                Tables\Filters\SelectFilter::make('clinic_id')->label(__('admin.fields.name_clinic'))->relationship('clinic', 'name'),
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
            'index' => Pages\ListPriceQuoteRequests::route('/'),
            'edit'  => Pages\EditPriceQuoteRequest::route('/{record}/edit'),
        ];
    }
}
