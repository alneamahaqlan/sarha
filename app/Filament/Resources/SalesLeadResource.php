<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\SalesLeadResource\Pages;
use App\Models\SalesLead;
use App\Models\Admin;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SalesLeadResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'sales_lead';
    protected static ?string $model = SalesLead::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-funnel';
    protected static string|\UnitEnum|null $navigationGroup = 'المبيعات والاشتراكات';
    protected static ?string $navigationLabel = 'خط المبيعات';
    protected static ?string $modelLabel = 'عميل محتمل';
    protected static ?string $pluralModelLabel = 'العملاء المحتملون';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('clinic_name')->label('اسم المجمع')->required(),
            Forms\Components\TextInput::make('contact_name')->label('اسم المسؤول'),
            Forms\Components\TextInput::make('phone')->label('رقم الهاتف')->required()->tel(),
            Forms\Components\TextInput::make('email')->label('البريد الإلكتروني')->email(),
            Forms\Components\Select::make('city_id')->label('المدينة')->relationship('city', 'name')->searchable(),
            Forms\Components\Select::make('status')
                ->label('الحالة')
                ->options([
                    'new' => 'جديد', 'contacted' => 'تم التواصل',
                    'interested' => 'مهتم', 'negotiating' => 'جاري التفاوض',
                    'converted' => 'تحول لعميل', 'lost' => 'خسر',
                ])->required(),
            Forms\Components\Select::make('assigned_to')
                ->label('مسؤول المبيعات')
                ->options(Admin::whereIn('role', ['sales', 'admin'])->pluck('name', 'id'))
                ->searchable(),
            Forms\Components\DateTimePicker::make('next_follow_up_at')->label('موعد المتابعة التالي'),
            Forms\Components\Textarea::make('notes')->label('ملاحظات')->rows(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clinic_name')->label('المجمع')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('contact_name')->label('المسؤول'),
                Tables\Columns\TextColumn::make('phone')->label('الهاتف'),
                Tables\Columns\TextColumn::make('city.name')->label('المدينة'),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'new' => 'جديد', 'contacted' => 'تم التواصل',
                        'interested' => 'مهتم', 'negotiating' => 'جاري التفاوض',
                        'converted' => 'تحول لعميل', 'lost' => 'خسر', default => $state,
                    })
                    ->color(fn($state) => match($state) {
                        'new' => 'info', 'contacted', 'interested' => 'warning',
                        'negotiating' => 'primary', 'converted' => 'success', 'lost' => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('next_follow_up_at')->label('المتابعة التالية')->dateTime('Y/m/d H:i'),
                Tables\Columns\TextColumn::make('assignedAdmin.name')->label('المسؤول'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['new' => 'جديد', 'contacted' => 'تم التواصل', 'interested' => 'مهتم', 'negotiating' => 'جاري التفاوض', 'converted' => 'تحول', 'lost' => 'خسر']),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('next_follow_up_at', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesLeads::route('/'),
            'create' => Pages\CreateSalesLead::route('/create'),
            'edit' => Pages\EditSalesLead::route('/{record}/edit'),
        ];
    }
}
