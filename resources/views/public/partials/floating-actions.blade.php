{{--
    Floating action buttons (WhatsApp + Book now)
    Expects: $clinic (Clinic model)
--}}
@php
    $whatsappMessage = __('site.whatsapp_message', ['clinic' => $clinic->name]);
    $whatsappUrl = $clinic->whatsappLink($whatsappMessage);
    $directionsUrl = $clinic->directionsUrl();
@endphp

{{-- Desktop floating buttons (mobile uses the sticky bottom bar instead) --}}
<div class="fixed bottom-6 end-6 z-40 hidden sm:flex flex-col gap-3" x-data x-cloak>
    {{-- WhatsApp --}}
    @if($clinic->phone)
        <a href="{{ $whatsappUrl }}"
           target="_blank"
           rel="noopener"
           data-track="whatsapp" data-clinic="{{ $clinic->id }}"
           title="@lang('site.whatsapp_label')"
           class="group flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white px-4 py-3 rounded-full shadow-lg hover:shadow-xl transition-all">
            <svg class="w-6 h-6 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.97L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.42a8.225 8.225 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.24 8.23-1.48 0-2.93-.39-4.19-1.15l-.3-.17-3.12.82.83-3.04-.2-.32a8.188 8.188 0 0 1-1.26-4.38c.01-4.54 3.7-8.24 8.25-8.24M8.53 7.33c-.16 0-.43.06-.66.31-.22.25-.87.86-.87 2.07 0 1.22.89 2.39 1 2.56.14.17 1.76 2.67 4.25 3.73.59.27 1.05.42 1.41.53.59.19 1.13.16 1.56.1.48-.07 1.46-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.07-.1-.23-.16-.48-.27-.25-.14-1.47-.74-1.69-.82-.23-.08-.37-.12-.56.12-.16.25-.64.81-.78.97-.15.17-.29.19-.53.07-.26-.13-1.06-.39-2-1.23-.74-.66-1.23-1.47-1.38-1.72-.12-.24-.01-.39.11-.5.11-.11.27-.29.37-.44.13-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.11-.56-1.35-.77-1.84-.2-.48-.4-.42-.56-.43-.14 0-.3-.01-.47-.01"/>
            </svg>
            <span class="hidden md:inline text-sm font-semibold whitespace-nowrap">@lang('site.whatsapp_label')</span>
        </a>
    @endif

    {{-- Directions --}}
    @if($directionsUrl)
        <a href="{{ $directionsUrl }}"
           target="_blank"
           rel="noopener"
           data-track="directions" data-clinic="{{ $clinic->id }}"
           title="@lang('site.action_directions')"
           class="group flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-full shadow-lg hover:shadow-xl transition-all">
            <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z"/>
            </svg>
            <span class="hidden md:inline text-sm font-semibold whitespace-nowrap">@lang('site.action_directions')</span>
        </a>
    @endif

    {{-- Book now --}}
    <a href="{{ route('clinic.book.form', $clinic->slug) }}"
       data-track="booking" data-clinic="{{ $clinic->id }}"
       title="@lang('site.book_now_label')"
       class="group flex items-center gap-2 bg-sage-600 hover:bg-sage-700 text-white px-4 py-3 rounded-full shadow-lg hover:shadow-xl transition-all">
        <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        <span class="hidden md:inline text-sm font-semibold whitespace-nowrap">@lang('site.book_now_label')</span>
    </a>
</div>
