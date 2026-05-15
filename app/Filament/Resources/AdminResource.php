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
    protected static ?int $navigationSort = 5;

    private static function roleOptions(): array
    {
        return [
            'super_admin' => __('admin.admin_role.super_admin'),
            'admin'       => __('admin.admin_role.admin'),
            'sales'       => __('admin.admin_role.sales'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->label(__('admin.fields.name'))->required(),
            Forms\Components\TextInput::make('email')
                ->label(__('admin.fields.email'))->email()->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('password')
                ->label(__('admin.fields.password'))->password()
                ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                ->dehydrated(fn($state) => filled($state))
                ->required(fn(string $operation) => $operation === 'create'),
            Forms\Components\Select::make('role')
                ->label(__('admin.fields.role'))
                ->options(self::roleOptions())
                ->required(),
            Forms\Components\Toggle::make('is_active')->label(__('admin.fields.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('admin.fields.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label(__('admin.fields.email_short')),
                Tables\Columns\TextColumn::make('role')
                    ->label(__('admin.fields.role'))
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'super_admin' => 'danger',
                        'admin'       => 'primary',
                        'sales'       => 'warning',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn($state) => __('admin.admin_role.' . $state)),
                Tables\Columns\IconColumn::make('is_active')->label(__('admin.fields.is_active'))->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.fields.created_at'))->date('Y/m/d')->sortable(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit'   => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
