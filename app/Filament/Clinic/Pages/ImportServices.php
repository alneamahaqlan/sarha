<?php

namespace App\Filament\Clinic\Pages;

use App\Models\Service;
use App\Services\AnthropicService;
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

        // Parse CSV
        $handle = fopen($path, 'r');
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map('trim', $row);
        }
        fclose($handle);

        if (count($rows) < 2) {
            Notification::make()->title(__('admin.import_empty'))->danger()->send();
            return;
        }

        $this->headers = array_shift($rows);
        $this->rows = $rows;

        $ai = app(AnthropicService::class);
        if (! $ai->isConfigured()) {
            // Heuristic fallback
            $this->analysis = $this->heuristicMatch($this->headers);
            Notification::make()->title(__('admin.import_ai_disabled_heuristic'))->warning()->send();
            return;
        }

        try {
            $this->analysis = $ai->analyzeExcelColumns($this->headers, array_slice($rows, 0, 3));
            Notification::make()->title(__('admin.ai.excel_done'))->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title(__('admin.ai.failed'))->body($e->getMessage())->danger()->send();
            $this->analysis = $this->heuristicMatch($this->headers);
        }
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

        $clinicId = auth('clinic')->id();
        $imported = 0;

        foreach ($this->rows as $row) {
            $service = ['clinic_id' => $clinicId, 'is_active' => true];
            foreach ($mapping as $colIdx => $field) {
                $service[$field] = $row[$colIdx] ?? null;
            }
            if (empty($service['name'])) continue;
            // Strip non-numeric from prices
            foreach (['price', 'old_price'] as $priceField) {
                if (! empty($service[$priceField])) {
                    $service[$priceField] = (float) preg_replace('/[^\d.]/', '', $service[$priceField]);
                }
            }
            Service::create($service);
            $imported++;
        }

        $this->reset(['analysis', 'rows', 'headers', 'data']);
        Notification::make()
            ->title(__('admin.import_success', ['count' => $imported]))
            ->success()->send();
    }

    private function heuristicMatch(array $headers): array
    {
        $rules = [
            'name'        => ['/name|اسم/iu'],
            'price'       => ['/^price|سعر$|السعر/iu'],
            'old_price'   => ['/old|قديم|قبل/iu'],
            'description' => ['/desc|وصف|تفاصيل/iu'],
        ];

        return array_map(function ($col) use ($rules) {
            foreach ($rules as $field => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $col)) {
                        return [
                            'column'     => $col,
                            'mapped_to'  => $field,
                            'confidence' => 70,
                            'reason'     => 'Heuristic match',
                        ];
                    }
                }
            }
            return ['column' => $col, 'mapped_to' => null, 'confidence' => 0, 'reason' => 'No match'];
        }, $headers);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('analyze')->label(__('admin.actions.analyze_excel_ai'))->action('analyze')->color('primary'),
        ];
    }
}
