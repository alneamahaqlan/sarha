<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\ClinicResource\Pages;
use App\Models\Clinic;
use App\Models\City;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClinicResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'clinic';
    protected static ?string $model = Clinic::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المجمعات';
    protected static ?string $navigationLabel = 'المجمعات';
    protected static ?string $modelLabel = 'مجمع';
    protected static ?string $pluralModelLabel = 'المجمعات';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->tabs([
                Tabs\Tab::make('المعلومات الأساسية')->schema([
                    Forms\Components\TextInput::make('name')->label('اسم المجمع')->required()->maxLength(255),
                    Forms\Components\TextInput::make('slug')->label('الرابط المختصر')->unique(ignoreRecord: true)->maxLength(255),
                    Forms\Components\TextInput::make('phone')->label('رقم الهاتف')->required()->tel()->maxLength(20),
                    Forms\Components\TextInput::make('email')->label('البريد الإلكتروني')->email()->maxLength(255),
                    Forms\Components\TextInput::make('password')->label('كلمة المرور')->password()
                        ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                        ->dehydrated(fn($state) => filled($state))
                        ->required(fn(string $operation) => $operation === 'create'),
                    Forms\Components\Select::make('city_id')->label('المدينة')
                        ->options(City::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->required(),
                    Forms\Components\Textarea::make('address')->label('العنوان')->rows(2),
                    Forms\Components\Textarea::make('description')->label('وصف المجمع')->rows(4),
                ])->columns(2),
                Tabs\Tab::make('التصنيفات')->schema([
                    Forms\Components\CheckboxList::make('categories')->label('التخصصات')
                        ->relationship('categories', 'name')->searchable()->columns(3),
                ]),
                Tabs\Tab::make('الاشتراك والحالة')->schema([
                    Forms\Components\Select::make('status')->label('الحالة')
                        ->options(['pending' => 'قيد المراجعة', 'active' => 'نشط', 'suspended' => 'موقوف', 'rejected' => 'مرفوض'])->required(),
                    Forms\Components\Select::make('subscription_type')->label('نوع الاشتراك')
                        ->options(['basic' => 'أساسي (300 ريال)', 'premium' => 'مميز (400 ريال)']),
                    Forms\Components\DateTimePicker::make('subscription_starts_at')->label('بداية الاشتراك'),
                    Forms\Components\DateTimePicker::make('subscription_ends_at')->label('نهاية الاشتراك'),
                    Forms\Components\Toggle::make('is_featured')->label('مجمع مميز'),
                    Forms\Components\Textarea::make('rejection_reason')->label('سبب الرفض')
                        ->visible(fn(Forms\Get $get) => $get('status') === 'rejected')->rows(3),
                ])->columns(2),
                Tabs\Tab::make('وسائل التواصل')->schema([
                    Forms\Components\TextInput::make('website')->label('الموقع الإلكتروني')->url(),
                    Forms\Components\TextInput::make('instagram')->label('إنستقرام'),
                    Forms\Components\TextInput::make('twitter')->label('تويتر/X'),
                    Forms\Components\TextInput::make('snapchat')->label('سناب شات'),
                    Forms\Components\TextInput::make('google_place_id')->label('Google Place ID'),
                ])->columns(2),
                Tabs\Tab::make('الصور')->schema([
                    Forms\Components\FileUpload::make('logo')->label('الشعار')->image()->directory('clinics/logos'),
                    Forms\Components\FileUpload::make('gallery')->label('معرض الصور')
                        ->image()->multiple()->directory('clinics/gallery')->maxFiles(10),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')->label('الشعار')->circular(),
                Tables\Columns\TextColumn::make('name')->label('اسم المجمع')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('city.name')->label('المدينة')->sortable(),
                Tables\Columns\TextColumn::make('phone')->label('الهاتف'),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->color(fn($state) => match($state) {
                        'pending' => 'warning', 'active' => 'success',
                        'suspended', 'rejected' => 'danger', default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'pending' => 'قيد المراجعة', 'active' => 'نشط',
                        'suspended' => 'موقوف', 'rejected' => 'مرفوض', default => $state,
                    }),
                Tables\Columns\TextColumn::make('subscription_type')->label('الاشتراك')->badge()
                    ->color(fn($state) => match($state) {
                        'basic' => 'primary', 'premium' => 'warning', default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'basic' => 'أساسي', 'premium' => 'مميز', default => '—',
                    }),
                Tables\Columns\TextColumn::make('subscription_ends_at')->label('انتهاء الاشتراك')->dateTime('Y/m/d')->sortable(),
                Tables\Columns\IconColumn::make('is_featured')->label('مميزة')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ التسجيل')->dateTime('Y/m/d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['pending' => 'قيد المراجعة', 'active' => 'نشط', 'suspended' => 'موقوف', 'rejected' => 'مرفوض']),
                Tables\Filters\SelectFilter::make('subscription_type')->label('الاشتراك')
                    ->options(['basic' => 'أساسي', 'premium' => 'مميز']),
                Tables\Filters\SelectFilter::make('city_id')->label('المدينة')->relationship('city', 'name'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('activate')->label('تفعيل')
                    ->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()
                    ->visible(fn(Clinic $record) => $record->status !== 'active')
                    ->action(function (Clinic $record) {
                        $record->update(['status' => 'active']);
                        \App\Services\AuditLogService::log('activated_Clinic', $record);
                    }),
                \Filament\Actions\Action::make('suspend')->label('إيقاف')
                    ->icon('heroicon-o-no-symbol')->color('danger')->requiresConfirmation()
                    ->visible(fn(Clinic $record) => $record->status === 'active')
                    ->action(function (Clinic $record) {
                        $record->update(['status' => 'suspended']);
                        \App\Services\AuditLogService::log('suspended_Clinic', $record);
                    }),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('المعلومات الأساسية')->schema([
                Infolists\Components\TextEntry::make('name')->label('الاسم'),
                Infolists\Components\TextEntry::make('phone')->label('الهاتف'),
                Infolists\Components\TextEntry::make('email')->label('البريد'),
                Infolists\Components\TextEntry::make('city.name')->label('المدينة'),
                Infolists\Components\TextEntry::make('status')->label('الحالة'),
                Infolists\Components\TextEntry::make('subscription_type')->label('الاشتراك'),
                Infolists\Components\TextEntry::make('subscription_ends_at')->label('انتهاء الاشتراك')->dateTime(),
            ])->columns(3),
        ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClinics::route('/'),
            'create' => Pages\CreateClinic::route('/create'),
            'view' => Pages\ViewClinic::route('/{record}'),
            'edit' => Pages\EditClinic::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
