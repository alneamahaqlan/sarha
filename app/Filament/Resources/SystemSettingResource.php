<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\SystemSettingResource\Pages;
use App\Models\SystemSetting;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class SystemSettingResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'system_setting';
    protected static ?string $model = SystemSetting::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'إعدادات النظام';
    protected static ?int $navigationSort = 99;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('key')
                ->label(__('admin.fields.setting_key'))
                ->required()->disabled()->dehydrated(false),

            Forms\Components\TextInput::make('label')->label(__('admin.fields.label'))->required(),

            Forms\Components\Select::make('type')
                ->label(__('admin.fields.value_type'))
                ->options([
                    'string'  => 'string',
                    'integer' => 'integer',
                    'decimal' => 'decimal',
                    'boolean' => 'boolean',
                    'json'    => 'json',
                ])->required(),

            Forms\Components\TextInput::make('group')->label(__('admin.fields.group')),

            Forms\Components\Textarea::make('value')
                ->label(__('admin.fields.value'))->rows(3)->columnSpanFull(),

            Forms\Components\Textarea::make('description')
                ->label(__('admin.fields.description'))->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group')->label(__('admin.fields.group'))->badge()->sortable(),
                Tables\Columns\TextColumn::make('key')->label(__('admin.fields.setting_key'))->searchable()->copyable(),
                Tables\Columns\TextColumn::make('label')->label(__('admin.fields.label'))->searchable(),
                Tables\Columns\TextColumn::make('value')->label(__('admin.fields.value'))->limit(40),
                Tables\Columns\TextColumn::make('type')->label(__('admin.fields.value_type'))->badge()->color('gray'),
            ])
            ->defaultGroup('group')
            ->filters([
                Tables\Filters\SelectFilter::make('group')->label(__('admin.fields.group'))
                    ->options(fn() => SystemSetting::query()
                        ->whereNotNull('group')
                        ->distinct()->pluck('group', 'group')->toArray()),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->after(fn(SystemSetting $record) => Cache::forget('setting:' . $record->key)),
            ])
            ->defaultSort('group')
            ->paginated(false);
    }

    public static function canCreate(): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemSettings::route('/'),
            'edit'  => Pages\EditSystemSetting::route('/{record}/edit'),
        ];
    }
}
