{{--
    Sticky bottom action bar for mobile (sm:hidden). One-tap Call · WhatsApp ·
    Directions · Book — the core of the landing-page experience on phones.
    Expects: $clinic (Clinic model).
--}}
@php
    $directionsUrl = $clinic->directionsUrl();
    $whatsappUrl = $clinic->phone
        ? $clinic->whatsappLink(__('site.whatsapp_message', ['clinic' => $clinic->name]))
        : null;
@endphp

{{-- Spacer so the fixed bar never covers page content on mobile --}}
<div class="h-16 sm:hidden" aria-hidden="true"></div>

<div class="fixed bottom-0 inset-x-0 z-40 sm:hidden bg-white border-t border-gray-200 shadow-[0_-2px_10px_rgba(0,0,0,0.06)]">
    <div class="grid grid-cols-4 divide-x divide-gray-100 text-center" dir="ltr">
        @if($clinic->phone)
            <a href="tel:{{ $clinic->phone }}" data-track="call" data-clinic="{{ $clinic->id }}"
               class="flex flex-col items-center justify-center gap-1 py-2.5 text-sage-600 active:bg-sage-50">
                <x-icon name="phone" class="w-5 h-5" />
                <span class="text-[11px] font-semibold">@lang('site.action_call')</span>
            </a>
        @endif

        @if($whatsappUrl)
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" data-track="whatsapp" data-clinic="{{ $clinic->id }}"
               class="flex flex-col items-center justify-center gap-1 py-2.5 text-green-600 active:bg-green-50">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.97L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2"/>
                </svg>
                <span class="text-[11px] font-semibold">@lang('site.action_whatsapp')</span>
            </a>
        @endif

        @if($directionsUrl)
            <a href="{{ $directionsUrl }}" target="_blank" rel="noopener" data-track="directions" data-clinic="{{ $clinic->id }}"
               class="flex flex-col items-center justify-center gap-1 py-2.5 text-blue-600 active:bg-blue-50">
                <x-icon name="map" class="w-5 h-5" />
                <span class="text-[11px] font-semibold">@lang('site.action_directions')</span>
            </a>
        @endif

        <a href="{{ route('clinic.book.form', $clinic->slug) }}" data-track="booking" data-clinic="{{ $clinic->id }}"
           class="flex flex-col items-center justify-center gap-1 py-2.5 bg-sage-600 text-white active:bg-sage-700">
            <x-icon name="calendar" class="w-5 h-5" />
            <span class="text-[11px] font-semibold">@lang('site.book_now_label')</span>
        </a>
    </div>
</div>
