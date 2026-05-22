<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\SalesLeadResource\Pages;
use App\Models\Admin;
use App\Models\Clinic;
use App\Models\SalesLead;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesLeadResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'sales_lead';
    protected static ?string $model = SalesLead::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-funnel';
    protected static string|\UnitEnum|null $navigationGroup = 'المبيعات والاشتراكات';
    protected static ?int $navigationSort = 2;

    private static function leadStatusOptions(): array
    {
        return [
            'new'         => __('admin.status.new'),
            'contacted'   => __('admin.status.contacted'),
            'interested'  => __('admin.status.interested'),
            'negotiating' => __('admin.status.negotiating'),
            'converted'   => __('admin.status.converted'),
            'lost'        => __('admin.status.lost'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('clinic_name')->label(__('admin.fields.clinic_name'))->required(),
            Forms\Components\TextInput::make('contact_name')->label(__('admin.fields.contact_name')),
            Forms\Components\TextInput::make('phone')->label(__('admin.fields.phone'))->required()->tel(),
            Forms\Components\TextInput::make('email')->label(__('admin.fields.email'))->email(),
            Forms\Components\TextInput::make('license_number')->label(__('admin.fields.license_number')),
            Forms\Components\Select::make('city_id')->label(__('admin.fields.city'))->relationship('city', 'name')->searchable(),
            Forms\Components\TextInput::make('district')->label(__('admin.fields.district')),
            Forms\Components\Textarea::make('address')->label(__('admin.fields.address'))->rows(2),
            Forms\Components\Select::make('status')
                ->label(__('admin.fields.status'))
                ->options(self::leadStatusOptions())->required(),
            Forms\Components\Select::make('assigned_to')
                ->label(__('admin.fields.assigned_to'))
                ->options(Admin::whereIn('role', ['sales', 'admin'])->pluck('name', 'id'))
                ->searchable(),
            Forms\Components\DateTimePicker::make('next_follow_up_at')->label(__('admin.fields.next_follow_up_at')),
            Forms\Components\DateTimePicker::make('last_contact_at')->label(__('admin.fields.last_contact_at')),
            Forms\Components\Textarea::make('notes')->label(__('admin.fields.notes'))->rows(3),
            Forms\Components\Textarea::make('sales_notes')->label(__('admin.fields.sales_notes'))->rows(3),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clinic_name')->label(__('admin.fields.name_clinic'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('contact_name')->label(__('admin.fields.contact_name')),
                Tables\Columns\TextColumn::make('phone')->label(__('admin.fields.phone')),
                Tables\Columns\TextColumn::make('city.name')->label(__('admin.fields.city')),
                Tables\Columns\TextColumn::make('status')->label(__('admin.fields.status'))->badge()
                    ->formatStateUsing(fn($state) => __('admin.status.' . $state))
                    ->color(fn($state) => match($state) {
                        'new' => 'info', 'contacted', 'interested' => 'warning',
                        'negotiating' => 'primary', 'converted' => 'success', 'lost' => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('next_follow_up_at')->label(__('admin.fields.next_follow_up_short'))->dateTime('Y/m/d H:i'),
                Tables\Columns\TextColumn::make('assignedAdmin.name')->label(__('admin.fields.assigned_to_short')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label(__('admin.fields.status'))
                    ->options(self::leadStatusOptions()),
            ])
            ->actions([
                \Filament\Actions\Action::make('convert_to_basic')
                    ->label(__('admin.actions.convert_to_basic'))
                    ->icon('heroicon-o-arrow-right-circle')->color('warning')
                    ->visible(fn(SalesLead $r) => $r->status !== 'converted')
                    ->requiresConfirmation()
                    ->action(fn(SalesLead $r) => self::convertLead($r, 'basic')),

                \Filament\Actions\Action::make('convert_to_premium')
                    ->label(__('admin.actions.convert_to_premium'))
                    ->icon('heroicon-o-star')->color('success')
                    ->visible(fn(SalesLead $r) => $r->status !== 'converted')
                    ->requiresConfirmation()
                    ->action(fn(SalesLead $r) => self::convertLead($r, 'premium')),

                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('next_follow_up_at', 'asc');
    }

    private static function convertLead(SalesLead $lead, string $plan): void
    {
        $price = (int) ($plan === 'premium' ? 400 : 300);
        $days = 90;

        DB::transaction(function () use ($lead, $plan, $price, $days) {
            $clinic = Clinic::create([
                'name'                   => $lead->clinic_name,
                'slug'                   => Str::slug($lead->clinic_name) . '-' . Str::random(4),
                'phone'                  => $lead->phone,
                'email'                  => $lead->email ?: ('lead-' . $lead->id . '@saerha.sa'),
                'password'               => bcrypt(Str::random(12)),
                'city_id'                => $lead->city_id,
                'address'                => $lead->address,
                'status'                 => 'active',
                'subscription_type'      => $plan,
                'subscription_starts_at' => now(),
                'subscription_ends_at'   => now()->addDays($days),
            ]);

            Subscription::create([
                'clinic_id'  => $clinic->id,
                'type'       => $plan,
                'amount'     => $price,
                'starts_at'  => now(),
                'ends_at'    => now()->addDays($days),
                'status'     => 'active',
            ]);

            $lead->update(['status' => 'converted']);

            app(\App\Services\NotificationService::class)->leadConverted($lead, $clinic);
            app(\App\Services\NotificationService::class)->clinicApproved($clinic);
        });

        Notification::make()
            ->title(__('admin.lead_converted', ['clinic' => $lead->clinic_name]))
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSalesLeads::route('/'),
            'create' => Pages\CreateSalesLead::route('/create'),
            'edit'   => Pages\EditSalesLead::route('/{record}/edit'),
        ];
    }
}
