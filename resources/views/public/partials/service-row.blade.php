{{--
    A single service row in the public clinic page. Two columns: the left
    holds name + description + offer chip, the right holds the price + a
    prominent "احجز موعد" button (every service now exposes its own
    booking entry point — previously it was a faint text link customers
    routinely missed).
    Expects: $service, $clinic
--}}
<div class="py-3 flex items-start justify-between gap-4">
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
            <a href="{{ route('service.show', ['slug' => $clinic->slug, 'service' => $service->id]) }}"
               class="font-medium text-gray-800 hover:text-sage-700 transition-colors">{{ $service->name }}</a>
            <x-save-button :model="$service" type="service" class="flex-shrink-0" />
        </div>
        @if($service->description)
            <p class="text-sm text-gray-500 mt-0.5">{{ $service->description }}</p>
        @endif
        @if($service->price_includes)
            <p class="text-xs text-emerald-700 mt-1">
                <span class="font-medium">@lang('site.price_includes'):</span> {{ $service->price_includes }}
            </p>
        @endif
        @if($service->price_excludes)
            <p class="text-xs text-gray-400 mt-0.5">
                <span class="font-medium">@lang('site.price_excludes'):</span> {{ $service->price_excludes }}
            </p>
        @endif
    </div>
    <div class="text-end flex-shrink-0 flex flex-col items-end gap-2 min-w-[8rem]">
        @if($service->price)
            <div>
                <span class="text-sage-700 font-bold whitespace-nowrap">
                    @if($service->price_from)
                        <span class="text-xs font-normal text-gray-500">@lang('site.price_from')</span>
                    @endif
                    {{ number_format($service->price) }}
                    <span class="text-xs font-normal"><x-riyal /></span>
                </span>
            </div>
            <a href="{{ route('clinic.book.form', ['slug' => $clinic->slug, 'service' => $service->id]) }}"
               data-track="booking" data-clinic="{{ $clinic->id }}"
               class="inline-flex items-center gap-1.5 bg-sage-600 hover:bg-sage-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition-colors whitespace-nowrap">
                <x-icon name="calendar" class="w-3.5 h-3.5" />
                @lang('site.book_appointment')
            </a>
        @else
            <span class="text-gray-400 text-sm">@lang('site.call_for_inquiry')</span>
            <a href="{{ route('clinic.book.form', ['slug' => $clinic->slug, 'service' => $service->id]) }}"
               data-track="booking" data-clinic="{{ $clinic->id }}"
               class="inline-flex items-center gap-1.5 bg-sage-600 hover:bg-sage-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition-colors whitespace-nowrap">
                <x-icon name="calendar" class="w-3.5 h-3.5" />
                @lang('site.book_appointment')
            </a>
        @endif
    </div>
</div>
