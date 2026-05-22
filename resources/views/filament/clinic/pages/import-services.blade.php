<x-filament-panels::page>
    {{-- Step 1: Upload --}}
    <form wire:submit="analyze">
        {{ $this->form }}
        <div class="mt-4 flex gap-3">
            <x-filament::button type="submit" color="primary" icon="heroicon-o-sparkles">
                {{ __('admin.actions.analyze_excel_ai') }}
            </x-filament::button>
        </div>
    </form>

    {{-- Step 2: Analysis results --}}
    @if($analysis)
        <div class="mt-8 bg-white dark:bg-gray-900 rounded-xl shadow-sm p-6 ring-1 ring-gray-200 dark:ring-gray-700">
            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">
                {{ __('admin.import_analysis_results') }}
            </h3>
            <div class="space-y-2">
                @foreach($analysis as $row)
                    @php
                        $confidence = (int) ($row['confidence'] ?? 0);
                        $color = $confidence >= 80 ? 'green' : ($confidence >= 50 ? 'amber' : 'gray');
                    @endphp
                    <div class="flex items-center justify-between gap-3 p-3 rounded-lg border border-gray-100 dark:border-gray-800">
                        <div class="flex-1">
                            <p class="font-semibold text-sm">{{ $row['column'] }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $row['reason'] ?? '' }}</p>
                        </div>
                        <div class="text-sm">
                            @if(! empty($row['mapped_to']))
                                <span class="bg-{{ $color }}-50 text-{{ $color }}-700 px-3 py-1 rounded-full font-mono text-xs">
                                    → {{ $row['mapped_to'] }}
                                </span>
                                <span class="text-xs text-gray-400 ms-2">{{ $confidence }}%</span>
                            @else
                                <span class="text-gray-400 text-xs">{{ __('admin.import_no_match') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 flex gap-3">
                <x-filament::button color="success" wire:click="importNow" icon="heroicon-o-arrow-down-tray">
                    {{ __('admin.import_import_now', ['count' => count($rows)]) }}
                </x-filament::button>
                <x-filament::button color="gray" wire:click="$set('analysis', null)" outlined>
                    {{ __('admin.actions.cancel') }}
                </x-filament::button>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
