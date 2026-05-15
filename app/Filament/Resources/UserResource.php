<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'user';
    protected static ?string $model = User::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المجمعات';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->label(__('admin.fields.name'))->required(),
            Forms\Components\TextInput::make('phone')->label(__('admin.fields.phone'))->required()->tel()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('email')->label(__('admin.fields.email'))->email(),
            Forms\Components\Toggle::make('is_active')->label(__('admin.fields.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('admin.fields.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->label(__('admin.fields.phone'))->searchable(),
                Tables\Columns\TextColumn::make('email')->label(__('admin.fields.email_short')),
                Tables\Columns\TextColumn::make('bookings_count')->label(__('admin.fields.bookings_count'))->counts('bookings'),
                Tables\Columns\IconColumn::make('is_active')->label(__('admin.fields.is_active'))->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.fields.created_at_register'))->date('Y/m/d')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('admin.fields.status'))
                    ->trueLabel(__('admin.status.true_active'))
                    ->falseLabel(__('admin.status.false_suspended')),
            ])
            ->actions([\Filament\Actions\EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
