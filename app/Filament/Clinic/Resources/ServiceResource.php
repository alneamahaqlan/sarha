<?php

namespace App\Filament\Clinic\Resources;

use App\Filament\Clinic\Resources\ServiceResource\Pages;
use App\Filament\Concerns\HasTranslatableLabels;
use App\Models\Service;
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
    protected static ?string $navigationLabel = 'خدماتي';
    protected static ?string $modelLabel = 'خدمة';
    protected static ?string $pluralModelLabel = 'الخدمات';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('clinic_id', auth('clinic')->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->label('اسم الخدمة')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->label('وصف الخدمة')->rows(3),
            Forms\Components\TextInput::make('price')
                ->label('السعر (ريال)')->required()->numeric()->minValue(0)->live(onBlur: true),
            Forms\Components\TextInput::make('old_price')
                ->label('السعر القديم (ريال)')->numeric()->minValue(0)->live(onBlur: true)
                ->helperText('عند تعبئته يجب أن يكون أكبر من السعر الحالي، ويصبح تاريخ انتهاء العرض إلزامياً')
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
                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => filled($get('old_price')))
                ->helperText('مطلوب عند وجود سعر قديم (لإظهار شارة العرض)'),
            Forms\Components\Toggle::make('is_active')->label('نشط')->default(true),
            Forms\Components\TextInput::make('sort_order')->label('ترتيب العرض')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('اسم الخدمة')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('price')->label('السعر')->suffix(' ريال')->sortable(),
                Tables\Columns\TextColumn::make('old_price')->label('السعر القديم')->suffix(' ريال'),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('الترتيب')->sortable(),
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
