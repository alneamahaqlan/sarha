<x-filament-panels::page>
    <form wire:submit="send" class="space-y-6">
        {{ $this->form }}
        <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            @foreach($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>
</x-filament-panels::page>
