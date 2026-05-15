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
    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.clinic.pages.clinic-profile';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('admin.res_clinic_profile');
    }

    public function getTitle(): string
    {
        return __('admin.res_clinic_profile');
    }

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
                Forms\Components\TextInput::make('name')->label(__('admin.fields.name_clinic'))->required(),
                Forms\Components\TextInput::make('phone')->label(__('admin.fields.phone'))->required()->tel(),
                Forms\Components\TextInput::make('email')->label(__('admin.fields.email'))->email(),
                Forms\Components\Textarea::make('address')->label(__('admin.fields.address'))->rows(2),
                Forms\Components\Textarea::make('description')->label(__('admin.fields.description_clinic'))->rows(4),
                Forms\Components\TextInput::make('website')->label(__('admin.fields.website'))->url(),
                Forms\Components\TextInput::make('instagram')->label(__('admin.fields.instagram')),
                Forms\Components\TextInput::make('twitter')->label(__('admin.fields.twitter')),
                Forms\Components\TextInput::make('snapchat')->label(__('admin.fields.snapchat')),
                Forms\Components\FileUpload::make('logo')->label(__('admin.fields.logo'))->image()->directory('clinics/logos'),
                Forms\Components\TextInput::make('password')
                    ->label(__('admin.fields.new_password'))->password()
                    ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                    ->dehydrated(fn($state) => filled($state)),
            ])->columns(2);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        auth('clinic')->user()->update(array_filter($data, fn($v) => $v !== null));

        Notification::make()->title(__('admin.validation.profile_saved'))->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label(__('admin.actions.save_changes'))
                ->action('save'),
        ];
    }
}
