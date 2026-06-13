<div x-data="{
        open: @entangle('open'),
        // Smooth-stick the messages list to the bottom whenever something
        // new lands (user turn, assistant reply, the 'Searching…' spinner).
        // Uses a MutationObserver so we catch DOM additions from Livewire
        // re-renders without needing to wire a $watch on every message field.
        scrollChat() {
            this.$nextTick(() => {
                const el = this.$refs.messagesScroll;
                if (el) el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
            });
        },
    }"
    x-init="
        // Initial stick + observe every future mutation inside the list.
        scrollChat();
        const el = $refs.messagesScroll;
        if (el) new MutationObserver(() => scrollChat()).observe(el, { childList: true, subtree: true, characterData: true });
        // Also stick after every Livewire round-trip so the spinner that
        // appears mid-request scrolls into view smoothly too.
        Livewire.hook('commit', ({ succeed }) => succeed(() => scrollChat()));
        // And stick when the chat is reopened (messages were scrolled before close).
        $watch('open', v => v && scrollChat());
    "
    @open-ai-chat.window="open = true" class="fixed bottom-6 start-6 z-40" x-cloak>

    {{-- Floating button --}}
    <button type="button"
            @click="open = ! open"
            x-show="! open"
            class="group bg-gradient-to-br from-plum-primary to-plum-deep hover:from-plum-deep hover:to-plum-deep text-white w-14 h-14 rounded-full shadow-xl flex items-center justify-center transition-all relative">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m12.728 0l-.707.707M5.05 5.05l-.707-.707M9 11a3 3 0 116 0c0 1.657-1.343 3-3 4-1.657 1-3 2.343-3 4"/>
        </svg>
        <span class="absolute -top-1 -end-1 w-3 h-3 bg-gold-primary rounded-full animate-ping"></span>
        <span class="absolute -top-1 -end-1 w-3 h-3 bg-gold-primary rounded-full"></span>
    </button>

    {{-- Chat window --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="bg-white rounded-2xl shadow-2xl w-96 max-w-[calc(100vw-3rem)] h-[600px] max-h-[calc(100vh-6rem)] flex flex-col overflow-hidden ring-1 ring-gray-200">

        {{-- Header --}}
        <div class="bg-gradient-to-r from-plum-primary to-plum-deep text-white p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center"><x-icon name="cpu" class="w-5 h-5" /></div>
                <div>
                    <h3 class="font-bold text-sm">@lang('site.ai_chat_title')</h3>
                    <p class="text-xs text-plum-whisper">@lang('site.ai_chat_subtitle')</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if(! empty($this->messages))
                    <button type="button"
                            wire:click="reset_"
                            wire:confirm="@lang('site.ai_new_chat_confirm')"
                            title="@lang('site.ai_new_chat')"
                            class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>
                @endif
                <button type="button" @click="open = false" class="text-white/80 hover:text-white text-xl">✕</button>
            </div>
        </div>

        {{-- Messages --}}
        <div x-ref="messagesScroll" class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50 scroll-smooth">
            @if(empty($this->messages))
                {{-- Welcome + quick prompts --}}
                <div class="bg-white rounded-lg p-4 shadow-sm">
                    <p class="text-sm text-gray-700 mb-3">@lang('site.ai_welcome')</p>
                    <div class="space-y-2">
                        @foreach($this->quickPrompts as $key => $labelKey)
                            <button type="button"
                                    wire:click="quickPrompt('{{ $key }}')"
                                    class="w-full text-start text-sm bg-plum-whisper hover:bg-plum-soft text-plum-deep px-3 py-2 rounded-lg transition-colors inline-flex items-center gap-2">
                                <x-icon name="light-bulb" class="w-4 h-4 shrink-0" /> @lang($labelKey)
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @foreach($this->messages as $msg)
                @if($msg['role'] === 'user')
                    <div class="flex justify-end">
                        <div class="bg-sage-600 text-white px-4 py-2 rounded-2xl rounded-be-sm max-w-[80%]">
                            <p class="text-sm">{{ $msg['content'] }}</p>
                        </div>
                    </div>
                @else
                    <div class="flex justify-start">
                        <div class="bg-white shadow-sm rounded-2xl rounded-bs-sm max-w-[85%] overflow-hidden">
                            <div class="p-3">
                                @if(($msg['kind'] ?? '') === 'rejected')
                                    <div class="flex items-center gap-1.5 text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2 text-xs mb-2">
                                        <x-icon name="warning" class="w-3.5 h-3.5 shrink-0" /> @lang('site.ai_medical_warning')
                                    </div>
                                @endif
                                <p class="text-sm text-gray-700 leading-relaxed">{{ $msg['content'] }}</p>
                            </div>

                            @if(! empty($msg['clinics']))
                                <div class="border-t border-gray-100 divide-y divide-gray-100">
                                    @foreach($msg['clinics'] as $c)
                                        <a href="{{ route('clinic.show', $c['slug']) }}"
                                           class="block p-3 hover:bg-gray-50 transition-colors">
                                            <p class="font-bold text-gray-800 text-sm">{{ $c['name'] }}</p>
                                            <div class="flex items-center gap-3 text-xs text-gray-500 mt-1">
                                                @if($c['city'])
                                                    <span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="w-3.5 h-3.5" /> {{ $c['city'] }}</span>
                                                @endif
                                                @if($c['rating'] > 0)
                                                    <span>★ {{ $c['rating'] }}</span>
                                                @endif
                                                @if($c['min_price'])
                                                    <span class="text-sage-700 font-semibold">
                                                        {{ __('site.starting_from', ['amount' => number_format($c['min_price'])]) }} <x-riyal />
                                                    </span>
                                                @endif
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach

            <div wire:loading wire:target="send, quickPrompt" class="flex items-center gap-2 text-xs text-gray-500">
                <div class="flex gap-1">
                    <span class="w-2 h-2 bg-plum-medium rounded-full animate-bounce"></span>
                    <span class="w-2 h-2 bg-plum-medium rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                    <span class="w-2 h-2 bg-plum-medium rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                </div>
                @lang('site.ai_thinking')
            </div>
        </div>

        {{-- Input --}}
        <form wire:submit.prevent="send" class="border-t border-gray-100 p-3 bg-white flex items-center gap-2">
            <input type="text"
                   wire:model="input"
                   placeholder="@lang('site.ai_input_placeholder')"
                   class="flex-1 border border-gray-200 rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-plum-medium">
            <button type="submit"
                    class="w-10 h-10 rounded-full bg-plum-primary hover:bg-plum-deep text-white flex items-center justify-center transition-colors">
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
            </button>
        </form>
    </div>
</div>
