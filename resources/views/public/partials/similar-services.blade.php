{{--
    Similar services in the same city — replaces the previous "similar
    clinics" block. Customer can price-compare without leaving the page;
    each card deep-links into the booking form for that exact service.
    Expects: $similarServices collection (can be empty).
--}}
@php $similarServices = $similarServices ?? collect(); @endphp
@if($similarServices->isNotEmpty())
<section class="mt-12">
    <div class="flex items-end justify-between gap-2 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">@lang('site.similar_services_title')</h2>
            <p class="text-sm text-gray-500 mt-1">@lang('site.similar_services_subtitle')</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($similarServices as $svc)
            @php
                $otherClinic = $svc->clinic;
                $emoji       = $svc->categories?->first()?->emoji;
            @endphp
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 hover:shadow-lg hover:ring-sage-200 transition-all overflow-hidden flex flex-col">
                <div class="relative aspect-[4/3] bg-gradient-to-br from-sage-mist to-gold-whisper flex items-center justify-center text-4xl">
                    @if($svc->image)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($svc->image) }}"
                             alt="" class="absolute inset-0 w-full h-full object-cover" loading="lazy">
                    @elseif($emoji)
                        <span aria-hidden="true">{{ $emoji }}</span>
                    @endif
                </div>
                <div class="p-4 flex-1 flex flex-col">
                    <h3 class="font-semibold text-gray-800 line-clamp-1">{{ $svc->name }}</h3>
                    @if($otherClinic)
                        {{-- Secondary "go to clinic page" link — the primary
                             action is the booking button below. Vertical
                             padding expands the tap zone from ~16px to ~36px
                             without inflating the card layout the way
                             min-h-touch would. --}}
                        <a href="{{ route('clinic.show', $otherClinic->slug) }}"
                           class="block text-xs text-gray-500 py-2 hover:text-sage-700 line-clamp-1 truncate">
                            {{ $otherClinic->name }}
                            @if($otherClinic->city)
                                <span class="text-gray-400">· {{ $otherClinic->city->display_name ?? $otherClinic->city->name }}</span>
                            @endif
                        </a>
                    @endif

                    <div class="mt-3 flex items-end justify-between gap-2">
                        <div>
                            <span class="text-sage-700 font-bold whitespace-nowrap">
                                {{ number_format($svc->price) }}
                                <span class="text-xs font-normal">@lang('site.currency_sar')</span>
                            </span>
                        </div>
                    </div>

                    @if($otherClinic)
                        <a href="{{ route('clinic.book.form', ['slug' => $otherClinic->slug, 'service' => $svc->id]) }}"
                           data-track="booking" data-clinic="{{ $otherClinic->id }}"
                           class="mt-3 inline-flex w-full items-center justify-center gap-1.5 min-h-touch bg-sage-600 hover:bg-sage-700 text-white text-sm font-semibold px-3 py-2 rounded-lg shadow-sm transition-colors">
                            <x-icon name="calendar" class="w-4 h-4" />
                            @lang('site.book_appointment')
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif
