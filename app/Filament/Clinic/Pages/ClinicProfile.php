<?php

namespace App\Filament\Clinic\Pages;

use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class ClinicProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';
    protected static ?string $navigationLabel = 'ملف العيادة';
    protected static ?string $title = 'ملف العيادة';
    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.clinic.pages.clinic-profile';

    public ?array $data = [];

    public function mount(): void
    {
        $clinic = auth('clinic')->user();
        $this->form->fill($clinic->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Forms\Components\TextInput::make('name')->label('اسم العيادة')->required(),
                Forms\Components\TextInput::make('phone')->label('رقم الهاتف')->required()->tel(),
                Forms\Components\TextInput::make('email')->label('البريد الإلكتروني')->email(),
                Forms\Components\Textarea::make('address')->label('العنوان')->rows(2),
                Forms\Components\Textarea::make('description')->label('وصف العيادة')->rows(4),
                Forms\Components\TextInput::make('website')->label('الموقع الإلكتروني')->url(),
                Forms\Components\TextInput::make('instagram')->label('إنستقرام'),
                Forms\Components\TextInput::make('twitter')->label('تويتر/X'),
                Forms\Components\TextInput::make('snapchat')->label('سناب شات'),
                Forms\Components\FileUpload::make('logo')->label('الشعار')->image()->directory('clinics/logos'),
                Forms\Components\TextInput::make('password')
                    ->label('كلمة مرور جديدة')->password()
                    ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                    ->dehydrated(fn($state) => filled($state)),
            ])->columns(2);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        auth('clinic')->user()->update(array_filter($data, fn($v) => $v !== null));

        Notification::make()->title('تم حفظ التغييرات بنجاح')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('حفظ التغييرات')
                ->action('save'),
        ];
    }
}
