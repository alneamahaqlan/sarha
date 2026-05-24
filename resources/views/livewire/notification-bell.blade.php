<div class="relative" x-data="{ open: @entangle('open') }" @click.outside="open = false">
    {{-- Bell button --}}
    <button type="button"
            @click="open = ! open"
            class="relative w-10 h-10 flex items-center justify-center rounded-full text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
            aria-label="{{ __('admin.notif.bell_label') }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if($this->unreadCount > 0)
            <span class="absolute top-1.5 end-1.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold animate-pulse">
                {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown --}}
    <div x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="absolute end-0 mt-2 w-96 max-w-[calc(100vw-2rem)] bg-white dark:bg-gray-900 rounded-xl shadow-2xl ring-1 ring-gray-200 dark:ring-gray-700 overflow-hidden z-50">

        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-800">
            <h3 class="font-bold text-gray-800 dark:text-gray-100 text-sm">@lang('admin.notif.bell_label')</h3>
            @if($this->unreadCount > 0)
                <button type="button" wire:click="markAllRead"
                        class="text-xs text-primary-600 hover:underline">
                    @lang('admin.notif.mark_all_read')
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse($this->notifications as $notif)
                <a href="{{ $notif->url ?? '#' }}"
                   wire:click="markAsRead({{ $notif->id }})"
                   class="flex items-start gap-3 px-4 py-3 border-b border-gray-50 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors {{ $notif->read_at ? '' : 'bg-blue-50/40 dark:bg-blue-900/10' }}">

                    {{-- Priority indicator --}}
                    <span class="mt-1 w-2 h-2 rounded-full flex-shrink-0
                        {{ ['low' => 'bg-gray-300', 'normal' => 'bg-blue-400', 'high' => 'bg-amber-500', 'urgent' => 'bg-red-500'][$notif->priority] ?? 'bg-blue-400' }}">
                    </span>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 leading-snug">{{ $notif->title }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed">{{ $notif->body }}</p>
                        <p class="text-[11px] text-gray-400 mt-1">{{ $notif->created_at?->diffForHumans() ?? __('admin.notif.just_now') }}</p>
                    </div>
                </a>
            @empty
                <div class="px-4 py-10 text-center">
                    <x-icon name="bell" class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                    <p class="text-sm text-gray-500">@lang('admin.notif.empty')</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
