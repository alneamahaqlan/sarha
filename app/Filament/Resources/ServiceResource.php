<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'service';
    protected static ?string $model = Service::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';
    protected static string|\UnitEnum|null $navigationGroup = 'المحتوى والخدمات';
    protected static ?string $navigationLabel = 'الخدمات';
    protected static ?string $modelLabel = 'خدمة';
    protected static ?string $pluralModelLabel = 'الخدمات';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('clinic_id')
                ->label('المجمع')->relationship('clinic', 'name')->searchable()->required(),
            Forms\Components\TextInput::make('name')->label('اسم الخدمة')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->label('الوصف')->rows(2),
            Forms\Components\TextInput::make('price')
                ->label('السعر (ريال)')->required()->numeric()->minValue(0)->live(onBlur: true),
            Forms\Components\TextInput::make('old_price')
                ->label('السعر القديم (ريال)')->numeric()->minValue(0)->live(onBlur: true)
                ->rules([
                    fn (\Filament\Schemas\Components\Utilities\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                        if ($value !== null && $value !== '' && (float) $value <= (float) $get('price')) {
                            $fail('السعر القديم يجب أن يكون أكبر من السعر الحالي');
                        }
                    },
                ]),
            Forms\Components\DateTimePicker::make('offer_expires_at')
                ->label('انتهاء العرض')
                ->minDate(now()->addDay())
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => filled($get('old_price'))),
            Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clinic.name')->label('المجمع')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->label('الخدمة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('price')->label('السعر')->suffix(' ريال')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الإضافة')->date('Y/m/d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('clinic_id')->label('المجمع')->relationship('clinic', 'name'),
            ])
            ->actions([\Filament\Actions\EditAction::make(), \Filament\Actions\DeleteAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
