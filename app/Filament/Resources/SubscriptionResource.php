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
    protected static ?string $navigationLabel = 'الاشتراكات';
    protected static ?string $modelLabel = 'اشتراك';
    protected static ?string $pluralModelLabel = 'الاشتراكات';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('clinic_id')
                ->label('العيادة')->relationship('clinic', 'name')->searchable()->required(),
            Forms\Components\Select::make('type')
                ->label('نوع الاشتراك')
                ->options(['basic' => 'أساسي (300 ريال)', 'premium' => 'مميز (400 ريال)'])
                ->required()->live()
                ->afterStateUpdated(fn($state, Forms\Set $set) => $set('amount', $state === 'premium' ? 400 : 300)),
            Forms\Components\TextInput::make('amount')->label('المبلغ (ريال)')->numeric()->required(),
            Forms\Components\DateTimePicker::make('starts_at')->label('بداية الاشتراك')->required(),
            Forms\Components\DateTimePicker::make('ends_at')->label('نهاية الاشتراك')->required(),
            Forms\Components\Select::make('status')
                ->label('الحالة')
                ->options(['active' => 'نشط', 'expired' => 'منتهي', 'cancelled' => 'ملغي', 'pending_payment' => 'في انتظار الدفع'])
                ->required(),
            Forms\Components\TextInput::make('moyasar_payment_id')->label('رقم معاملة ميسر'),
            Forms\Components\Textarea::make('notes')->label('ملاحظات')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clinic.name')->label('العيادة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->label('النوع')->badge()
                    ->formatStateUsing(fn($state) => $state === 'premium' ? 'مميز' : 'أساسي')
                    ->color(fn($state) => $state === 'premium' ? 'warning' : 'primary'),
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')->suffix(' ريال')->sortable(),
                Tables\Columns\TextColumn::make('starts_at')->label('البداية')->date('Y/m/d'),
                Tables\Columns\TextColumn::make('ends_at')->label('النهاية')->date('Y/m/d')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'active' => 'نشط', 'expired' => 'منتهي',
                        'cancelled' => 'ملغي', 'pending_payment' => 'في انتظار الدفع', default => $state,
                    })
                    ->color(fn($state) => match($state) {
                        'active' => 'success', 'expired', 'cancelled' => 'danger', default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الإنشاء')->date('Y/m/d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('النوع')->options(['basic' => 'أساسي', 'premium' => 'مميز']),
                Tables\Filters\SelectFilter::make('status')->label('الحالة')->options(['active' => 'نشط', 'expired' => 'منتهي', 'cancelled' => 'ملغي']),
            ])
            ->actions([\Filament\Actions\EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
