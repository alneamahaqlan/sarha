{{--
    A single service row in the public clinic page.
    Expects: $service, $clinic
--}}
<div class="py-3 flex items-center justify-between gap-4">
    <div class="flex-1 min-w-0">
        <p class="font-medium text-gray-800">{{ $service->name }}</p>
        @if($service->description)
            <p class="text-sm text-gray-500 mt-0.5">{{ $service->description }}</p>
        @endif
        @if($service->hasActiveOffer())
            <span class="inline-block mt-1 bg-red-50 text-red-700 text-xs px-2 py-0.5 rounded-full font-semibold">
                -{{ $service->discountPercentage() }}%
            </span>
        @endif
    </div>
    <div class="text-end flex-shrink-0">
        @if($service->price)
            <span class="text-teal-700 font-bold">
                @if($service->old_price)
                    <span class="line-through text-gray-400 text-sm me-1">{{ number_format($service->old_price) }}</span>
                @endif
                {{ number_format($service->price) }}
                <span class="text-xs font-normal">@lang('site.currency_sar')</span>
            </span>
            <a href="{{ route('clinic.book.form', ['slug' => $clinic->slug, 'service' => $service->id]) }}"
               data-track="booking" data-clinic="{{ $clinic->id }}"
               class="block mt-1 text-xs text-teal-600 hover:underline">
                @lang('site.book_appointment')
            </a>
        @else
            <span class="text-gray-400 text-sm">@lang('site.call_for_inquiry')</span>
        @endif
    </div>
</div>
