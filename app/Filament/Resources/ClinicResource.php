<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\ClinicResource\Pages;
use App\Models\Clinic;
use App\Models\City;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClinicResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'clinic';
    protected static ?string $model = Clinic::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المجمعات';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->tabs([
                Tabs\Tab::make(__('admin.fields.tabs_basic'))->schema([
                    Forms\Components\TextInput::make('name')->label(__('admin.fields.name_clinic'))->required()->maxLength(255),
                    Forms\Components\TextInput::make('slug')->label(__('admin.fields.slug'))->unique(ignoreRecord: true)->maxLength(255),
                    Forms\Components\TextInput::make('phone')->label(__('admin.fields.phone'))->required()->tel()->maxLength(20),
                    Forms\Components\TextInput::make('email')->label(__('admin.fields.email'))->email()->maxLength(255),
                    Forms\Components\TextInput::make('password')->label(__('admin.fields.password'))->password()
                        ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                        ->dehydrated(fn($state) => filled($state))
                        ->required(fn(string $operation) => $operation === 'create'),
                    Forms\Components\Select::make('city_id')->label(__('admin.fields.city'))
                        ->options(City::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->required(),
                    Forms\Components\Textarea::make('address')->label(__('admin.fields.address'))->rows(2),
                    Forms\Components\Textarea::make('description')->label(__('admin.fields.description_clinic'))->rows(4),
                ])->columns(2),
                Tabs\Tab::make(__('admin.fields.tabs_categories'))->schema([
                    Forms\Components\CheckboxList::make('categories')->label(__('admin.fields.categories'))
                        ->relationship('categories', 'name')->searchable()->columns(3),
                ]),
                Tabs\Tab::make(__('admin.fields.tabs_subscription'))->schema([
                    Forms\Components\Select::make('status')->label(__('admin.fields.status'))
                        ->options([
                            'pending'   => __('admin.status.pending'),
                            'active'    => __('admin.status.active'),
                            'suspended' => __('admin.status.suspended'),
                            'rejected'  => __('admin.status.rejected'),
                        ])->required(),
                    Forms\Components\Select::make('subscription_type')->label(__('admin.fields.subscription_type'))
                        ->options([
                            'basic'   => __('admin.plan.basic_priced'),
                            'premium' => __('admin.plan.premium_priced'),
                        ]),
                    Forms\Components\DateTimePicker::make('subscription_starts_at')->label(__('admin.fields.subscription_starts_at')),
                    Forms\Components\DateTimePicker::make('subscription_ends_at')->label(__('admin.fields.subscription_ends_at')),
                    Forms\Components\Toggle::make('is_featured')->label(__('admin.fields.is_featured_clinic')),
                    Forms\Components\Textarea::make('rejection_reason')->label(__('admin.fields.rejection_reason'))
                        ->visible(fn(Forms\Get $get) => $get('status') === 'rejected')->rows(3),
                ])->columns(2),
                Tabs\Tab::make(__('admin.fields.tabs_social'))->schema([
                    Forms\Components\TextInput::make('website')->label(__('admin.fields.website'))->url(),
                    Forms\Components\TextInput::make('instagram')->label(__('admin.fields.instagram')),
                    Forms\Components\TextInput::make('twitter')->label(__('admin.fields.twitter')),
                    Forms\Components\TextInput::make('snapchat')->label(__('admin.fields.snapchat')),
                    Forms\Components\TextInput::make('google_place_id')->label(__('admin.fields.google_place_id')),
                ])->columns(2),
                Tabs\Tab::make(__('admin.fields.tabs_images'))->schema([
                    Forms\Components\FileUpload::make('logo')->label(__('admin.fields.logo'))->image()->directory('clinics/logos'),
                    Forms\Components\FileUpload::make('gallery')->label(__('admin.fields.gallery'))
                        ->image()->multiple()->directory('clinics/gallery')->maxFiles(10),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')->label(__('admin.fields.logo'))->circular(),
                Tables\Columns\TextColumn::make('name')->label(__('admin.fields.name_clinic'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('city.name')->label(__('admin.fields.city'))->sortable(),
                Tables\Columns\TextColumn::make('phone')->label(__('admin.fields.phone'))->toggleable(),
                Tables\Columns\TextColumn::make('status')->label(__('admin.fields.status'))->badge()
                    ->color(fn($state) => match($state) {
                        'pending' => 'warning', 'active' => 'success',
                        'suspended', 'rejected' => 'danger', default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'pending'   => __('admin.status.pending'),
                        'active'    => __('admin.status.active'),
                        'suspended' => __('admin.status.suspended'),
                        'rejected'  => __('admin.status.rejected'),
                        default     => $state,
                    }),
                Tables\Columns\TextColumn::make('subscription_type')->label(__('admin.fields.subscription'))->badge()
                    ->color(fn($state) => match($state) {
                        'basic' => 'primary', 'premium' => 'warning', default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'basic'   => __('admin.plan.basic'),
                        'premium' => __('admin.plan.premium'),
                        default   => __('admin.fields.default_dash'),
                    }),
                Tables\Columns\TextColumn::make('subscription_ends_at')->label(__('admin.fields.subscription_ends_short'))->dateTime('Y/m/d')->sortable(),
                Tables\Columns\IconColumn::make('is_featured')->label(__('admin.fields.is_featured'))->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.fields.created_at_register'))->dateTime('Y/m/d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label(__('admin.fields.status'))
                    ->options([
                        'pending'   => __('admin.status.pending'),
                        'active'    => __('admin.status.active'),
                        'suspended' => __('admin.status.suspended'),
                        'rejected'  => __('admin.status.rejected'),
                    ]),
                Tables\Filters\SelectFilter::make('subscription_type')->label(__('admin.fields.subscription'))
                    ->options([
                        'basic'   => __('admin.plan.basic'),
                        'premium' => __('admin.plan.premium'),
                    ]),
                Tables\Filters\SelectFilter::make('city_id')->label(__('admin.fields.city'))->relationship('city', 'name'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),

                \Filament\Actions\Action::make('approve')
                    ->label(__('admin.actions.approve'))
                    ->icon('heroicon-o-check-badge')->color('success')->requiresConfirmation()
                    ->visible(fn(Clinic $record) => $record->status === 'pending')
                    ->action(function (Clinic $record) {
                        $record->update([
                            'status'                 => 'active',
                            'subscription_type'      => $record->subscription_type ?? 'basic',
                            'subscription_starts_at' => now(),
                            'subscription_ends_at'   => now()->addDays(90),
                        ]);
                        \App\Models\Subscription::create([
                            'clinic_id' => $record->id,
                            'type'      => $record->subscription_type ?? 'basic',
                            'amount'    => ($record->subscription_type ?? 'basic') === 'premium' ? 400 : 300,
                            'starts_at' => now(),
                            'ends_at'   => now()->addDays(90),
                            'status'    => 'active',
                        ]);
                        \App\Services\AuditLogService::log('approved_Clinic', $record);
                        \Filament\Notifications\Notification::make()
                            ->title(__('admin.booking_approved'))->success()->send();
                    }),

                \Filament\Actions\Action::make('reject')
                    ->label(__('admin.actions.reject'))
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn(Clinic $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label(__('admin.fields.rejection_reason'))
                            ->required()->rows(3),
                    ])
                    ->action(function (Clinic $record, array $data) {
                        $record->update([
                            'status'           => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        \App\Services\AuditLogService::log('rejected_Clinic', $record);
                    }),

                \Filament\Actions\Action::make('activate')->label(__('admin.actions.activate'))
                    ->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()
                    ->visible(fn(Clinic $record) => in_array($record->status, ['suspended', 'rejected']))
                    ->action(function (Clinic $record) {
                        $record->update(['status' => 'active']);
                        \App\Services\AuditLogService::log('activated_Clinic', $record);
                    }),

                \Filament\Actions\Action::make('suspend')->label(__('admin.actions.suspend'))
                    ->icon('heroicon-o-no-symbol')->color('danger')->requiresConfirmation()
                    ->visible(fn(Clinic $record) => $record->status === 'active')
                    ->action(function (Clinic $record) {
                        $record->update(['status' => 'suspended']);
                        \App\Services\AuditLogService::log('suspended_Clinic', $record);
                    }),

                \Filament\Actions\Action::make('extend_30')
                    ->label(__('admin.actions.extend_30'))
                    ->icon('heroicon-o-plus-circle')->color('warning')->requiresConfirmation()
                    ->visible(fn(Clinic $record) => $record->status === 'active')
                    ->action(function (Clinic $record) {
                        $base = $record->subscription_ends_at?->isFuture()
                            ? $record->subscription_ends_at
                            : now();
                        $record->update(['subscription_ends_at' => $base->copy()->addDays(30)]);
                        \App\Services\AuditLogService::log('extended_Clinic_30', $record);
                        \Filament\Notifications\Notification::make()
                            ->title(__('admin.subscription_extended', ['days' => 30]))->success()->send();
                    }),

                \Filament\Actions\Action::make('extend_90')
                    ->label(__('admin.actions.extend_90'))
                    ->icon('heroicon-o-plus-circle')->color('primary')->requiresConfirmation()
                    ->visible(fn(Clinic $record) => $record->status === 'active')
                    ->action(function (Clinic $record) {
                        $base = $record->subscription_ends_at?->isFuture()
                            ? $record->subscription_ends_at
                            : now();
                        $record->update(['subscription_ends_at' => $base->copy()->addDays(90)]);
                        \App\Services\AuditLogService::log('extended_Clinic_90', $record);
                        \Filament\Notifications\Notification::make()
                            ->title(__('admin.subscription_extended', ['days' => 90]))->success()->send();
                    }),

                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make(__('admin.fields.tabs_basic'))->schema([
                Infolists\Components\TextEntry::make('name')->label(__('admin.fields.name')),
                Infolists\Components\TextEntry::make('phone')->label(__('admin.fields.phone')),
                Infolists\Components\TextEntry::make('email')->label(__('admin.fields.email_short')),
                Infolists\Components\TextEntry::make('city.name')->label(__('admin.fields.city')),
                Infolists\Components\TextEntry::make('status')->label(__('admin.fields.status'))
                    ->formatStateUsing(fn($state) => __('admin.status.' . $state)),
                Infolists\Components\TextEntry::make('subscription_type')->label(__('admin.fields.subscription'))
                    ->formatStateUsing(fn($state) => $state ? __('admin.plan.' . $state) : __('admin.fields.default_dash')),
                Infolists\Components\TextEntry::make('subscription_ends_at')->label(__('admin.fields.subscription_ends_short'))->dateTime(),
            ])->columns(3),
        ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListClinics::route('/'),
            'create' => Pages\CreateClinic::route('/create'),
            'view'   => Pages\ViewClinic::route('/{record}'),
            'edit'   => Pages\EditClinic::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
