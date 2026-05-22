<?php

namespace App\Filament\Pages;

use App\Services\MassNotifyService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class MassNotify extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';
    protected static string|\UnitEnum|null $navigationGroup = 'إعدادات النظام';
    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.mass-notify';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('admin.mass_notify_title');
    }

    public function getTitle(): string
    {
        return __('admin.mass_notify_title');
    }

    public function mount(): void
    {
        $this->form->fill(['priority' => 'normal']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Forms\Components\Select::make('audience')
                    ->label(__('admin.mass_notify_audience'))
                    ->options([
                        'all'        => __('admin.mass_notify_audience_all'),
                        'premium'    => __('admin.mass_notify_audience_premium'),
                        'basic'      => __('admin.mass_notify_audience_basic'),
                        'expiring'   => __('admin.mass_notify_audience_expiring'),
                    ])
                    ->required(),

                Forms\Components\Select::make('priority')
                    ->label(__('admin.fields.priority'))
                    ->options([
                        'low'    => __('admin.complaint_priority.low'),
                        'normal' => __('admin.complaint_priority.medium'),
                        'high'   => __('admin.complaint_priority.high'),
                        'urgent' => __('admin.mass_notify_urgent'),
                    ])
                    ->default('normal')
                    ->required(),

                Forms\Components\TextInput::make('title')
                    ->label(__('admin.fields.subject'))
                    ->required()->maxLength(255)->columnSpanFull(),

                Forms\Components\Textarea::make('body')
                    ->label(__('admin.fields.description'))
                    ->rows(4)->required()->maxLength(2000)->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function send(): void
    {
        $payload = $this->form->getState();

        $sent = app(MassNotifyService::class)->send($payload);

        Notification::make()
            ->title(__('admin.mass_notify_sent', ['count' => $sent]))
            ->success()
            ->send();

        $this->form->fill(['priority' => 'normal']);
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('send')
                ->label(__('admin.mass_notify_send'))
                ->action('send')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(__('admin.mass_notify_confirm_heading'))
                ->modalDescription(__('admin.mass_notify_confirm_body')),
        ];
    }
}
