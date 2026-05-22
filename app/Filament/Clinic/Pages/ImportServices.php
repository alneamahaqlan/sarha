<?php

namespace App\Filament\Clinic\Pages;

use App\Services\ImportServicesService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class ImportServices extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string|\UnitEnum|null $navigationGroup = 'إدارة خدماتي';
    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.clinic.pages.import-services';

    public ?array $data = [];
    public ?array $analysis = null;
    public ?array $rows = null;
    public ?array $headers = null;

    public static function getNavigationLabel(): string
    {
        return __('admin.import_services_title');
    }

    public function getTitle(): string
    {
        return __('admin.import_services_title');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()->schema([
                    FileUpload::make('file')
                        ->label(__('admin.fields.file'))
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                        ->maxSize(2048)
                        ->required(),
                ]),
            ]);
    }

    public function analyze(): void
    {
        $payload = $this->form->getState();
        $file = data_get($payload, 'file');
        if (! $file) {
            Notification::make()->title(__('admin.import_no_file'))->warning()->send();
            return;
        }

        $path = is_string($file) ? storage_path('app/public/' . $file) : $file->getRealPath();
        if (! is_readable($path)) {
            Notification::make()->title(__('admin.import_unreadable'))->danger()->send();
            return;
        }

        $service = app(ImportServicesService::class);
        $parsed = $service->parseCsv($path);

        if (empty($parsed['headers'])) {
            Notification::make()->title(__('admin.import_empty'))->danger()->send();
            return;
        }

        $this->headers = $parsed['headers'];
        $this->rows = $parsed['rows'];
        $this->analysis = $service->analyzeColumns($this->headers, $this->rows);

        Notification::make()->title(__('admin.ai.excel_done'))->success()->send();
    }

    public function importNow(): void
    {
        if (! $this->analysis || ! $this->rows) {
            Notification::make()->title(__('admin.import_analyze_first'))->warning()->send();
            return;
        }

        $mapping = collect($this->analysis)
            ->whereNotNull('mapped_to')
            ->where('confidence', '>=', 50)
            ->mapWithKeys(fn($m) => [array_search($m['column'], $this->headers) => $m['mapped_to']])
            ->toArray();

        if (empty($mapping)) {
            Notification::make()->title(__('admin.import_no_mapping'))->danger()->send();
            return;
        }

        $imported = app(ImportServicesService::class)
            ->importRows((int) auth('clinic')->id(), $this->headers, $this->rows, $mapping);

        $this->reset(['analysis', 'rows', 'headers', 'data']);
        Notification::make()
            ->title(__('admin.import_success', ['count' => $imported]))
            ->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('analyze')->label(__('admin.actions.analyze_excel_ai'))->action('analyze')->color('primary'),
        ];
    }
}
