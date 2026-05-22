<?php

namespace App\Filament\Clinic\Resources;

use App\Filament\Clinic\Resources\ServiceResource\Pages;
use App\Filament\Concerns\HasTranslatableLabels;
use App\Models\CustomCategory;
use App\Models\Service;
use App\Models\SubClinic;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'my_service';
    protected static ?string $model = Service::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';
    protected static string|\UnitEnum|null $navigationGroup = 'إدارة خدماتي';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('clinic_id', auth('clinic')->id());
    }

    public static function form(Schema $schema): Schema
    {
        $clinicId = auth('clinic')->id();
        return $schema->components([
            Forms\Components\TextInput::make('name')->label(__('admin.fields.name_service'))->required()->maxLength(255),
            Forms\Components\Select::make('custom_category_id')
                ->label(__('admin.fields.category'))
                ->options(CustomCategory::where('clinic_id', $clinicId)->where('is_active', true)->pluck('name', 'id'))
                ->searchable(),
            Forms\Components\Select::make('sub_clinic_id')
                ->label(__('admin.fields.sub_clinic'))
                ->options(SubClinic::where('clinic_id', $clinicId)->where('is_active', true)->pluck('name', 'id'))
                ->searchable(),
            Forms\Components\Textarea::make('description')->label(__('admin.fields.description_service'))->rows(3)->columnSpanFull(),
            Forms\Components\TextInput::make('price')
                ->label(__('admin.fields.price_sar'))->required()->numeric()->minValue(0)->live(onBlur: true),
            Forms\Components\TextInput::make('old_price')
                ->label(__('admin.fields.old_price_sar'))->numeric()->minValue(0)->live(onBlur: true)
                ->helperText(__('admin.validation.old_price_helper'))
                ->rules([
                    fn (\Filament\Schemas\Components\Utilities\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                        if ($value !== null && $value !== '' && (float) $value <= (float) $get('price')) {
                            $fail(__('admin.validation.old_price_higher'));
                        }
                    },
                ]),
            Forms\Components\DateTimePicker::make('offer_expires_at')
                ->label(__('admin.fields.offer_expires_at'))
                ->minDate(now()->addDay())
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => filled($get('old_price')))
                ->helperText(__('admin.validation.offer_expires_helper')),
            Forms\Components\Toggle::make('is_active')->label(__('admin.fields.is_active'))->default(true),
            Forms\Components\TextInput::make('sort_order')->label(__('admin.fields.sort_order'))->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('admin.fields.name_service'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('price')->label(__('admin.fields.price'))->suffix(' ' . __('site.currency_sar'))->sortable(),
                Tables\Columns\TextColumn::make('old_price')->label(__('admin.fields.old_price'))->suffix(' ' . __('site.currency_sar')),
                Tables\Columns\IconColumn::make('is_active')->label(__('admin.fields.is_active'))->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label(__('admin.fields.sort_order_short'))->sortable(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageServices::route('/'),
        ];
    }
}
