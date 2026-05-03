<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\AdminResource\Pages;
use App\Models\Admin;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AdminResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'admin';
    protected static ?string $model = Admin::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static string|\UnitEnum|null $navigationGroup = 'إعدادات النظام';
    protected static ?string $navigationLabel = 'المسؤولون';
    protected static ?string $modelLabel = 'مسؤول';
    protected static ?string $pluralModelLabel = 'المسؤولون';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->label('الاسم')->required(),
            Forms\Components\TextInput::make('email')
                ->label('البريد الإلكتروني')->email()->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('password')
                ->label('كلمة المرور')->password()
                ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                ->dehydrated(fn($state) => filled($state))
                ->required(fn(string $operation) => $operation === 'create'),
            Forms\Components\Select::make('role')
                ->label('الصلاحية')
                ->options(['super_admin' => 'مدير عام', 'admin' => 'مدير', 'sales' => 'مبيعات'])
                ->required(),
            Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label('البريد'),
                Tables\Columns\TextColumn::make('role')
                    ->label('الصلاحية')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'super_admin' => 'danger',
                        'admin' => 'primary',
                        'sales' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'super_admin' => 'مدير عام',
                        'admin' => 'مدير',
                        'sales' => 'مبيعات',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الإنشاء')->date('Y/m/d')->sortable(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit' => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
