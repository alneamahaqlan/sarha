<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'subscription';
    protected static ?string $model = Subscription::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static string|\UnitEnum|null $navigationGroup = 'المبيعات والاشتراكات';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('clinic_id')
                ->label(__('admin.fields.name_clinic'))->relationship('clinic', 'name')->searchable()->required(),
            Forms\Components\Select::make('type')
                ->label(__('admin.fields.subscription_type'))
                ->options([
                    'basic'   => __('admin.plan.basic_priced'),
                    'premium' => __('admin.plan.premium_priced'),
                ])
                ->required()->live()
                ->afterStateUpdated(fn($state, Forms\Set $set) => $set('amount', $state === 'premium' ? 400 : 300)),
            Forms\Components\TextInput::make('amount')->label(__('admin.fields.amount_sar'))->numeric()->required(),
            Forms\Components\DateTimePicker::make('starts_at')->label(__('admin.fields.subscription_starts_at'))->required(),
            Forms\Components\DateTimePicker::make('ends_at')->label(__('admin.fields.subscription_ends_at'))->required(),
            Forms\Components\Select::make('status')
                ->label(__('admin.fields.status'))
                ->options([
                    'active'          => __('admin.status.active'),
                    'expired'         => __('admin.status.expired'),
                    'cancelled'       => __('admin.status.cancelled'),
                    'pending_payment' => __('admin.status.pending_payment'),
                ])
                ->required(),
            Forms\Components\TextInput::make('moyasar_payment_id')->label(__('admin.fields.moyasar_payment_id')),
            Forms\Components\Textarea::make('notes')->label(__('admin.fields.notes'))->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clinic.name')->label(__('admin.fields.name_clinic'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->label(__('admin.fields.plan_type'))->badge()
                    ->formatStateUsing(fn($state) => __('admin.plan.' . $state))
                    ->color(fn($state) => $state === 'premium' ? 'warning' : 'primary'),
                Tables\Columns\TextColumn::make('amount')->label(__('admin.fields.amount'))->suffix(' ' . __('site.currency_sar'))->sortable(),
                Tables\Columns\TextColumn::make('starts_at')->label(__('admin.fields.starts_at'))->date('Y/m/d'),
                Tables\Columns\TextColumn::make('ends_at')->label(__('admin.fields.ends_at'))->date('Y/m/d')->sortable(),
                Tables\Columns\TextColumn::make('status')->label(__('admin.fields.status'))->badge()
                    ->formatStateUsing(fn($state) => __('admin.status.' . $state))
                    ->color(fn($state) => match($state) {
                        'active' => 'success', 'expired', 'cancelled' => 'danger', default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.fields.created_at'))->date('Y/m/d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label(__('admin.fields.plan_type'))
                    ->options([
                        'basic'   => __('admin.plan.basic'),
                        'premium' => __('admin.plan.premium'),
                    ]),
                Tables\Filters\SelectFilter::make('status')->label(__('admin.fields.status'))
                    ->options([
                        'active'    => __('admin.status.active'),
                        'expired'   => __('admin.status.expired'),
                        'cancelled' => __('admin.status.cancelled'),
                    ]),
            ])
            ->actions([\Filament\Actions\EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit'   => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
