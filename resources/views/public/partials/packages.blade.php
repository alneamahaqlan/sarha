{{--
    Packages tab — bundles of services sold as a single deal. Used to
    live next to "offers" in one tab; now its own tab so visitors can
    browse promotions vs. bundles independently.
    Expects: $clinic (with packages.services loaded)
--}}
@if($clinic->packages->isEmpty())
    <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
        @lang('site.no_packages_yet')
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach($clinic->packages as $package)
            <div class="bg-white rounded-xl shadow-sm p-5 border border-sage-100">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="font-bold text-gray-800">{{ $package->name }}</h3>
                    @if($package->discountPercentage())
                        <span class="bg-red-50 text-red-700 text-xs px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
                            -{{ $package->discountPercentage() }}%
                        </span>
                    @endif
                </div>
                @if($package->description)
                    <p class="text-sm text-gray-500 mt-1">{{ $package->description }}</p>
                @endif
                @if($package->services->isNotEmpty())
                    <ul class="mt-3 space-y-1">
                        @foreach($package->services as $svc)
                            <li class="flex items-center gap-2 text-sm text-gray-700">
                                <x-icon name="check-circle" class="w-4 h-4 text-emerald-500 flex-shrink-0" />
                                {{ $svc->name }}
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-4 flex items-end justify-between">
                    <div>
                        @if($package->old_price)
                            <span class="block text-[11px] text-gray-400">
                                @lang('site.price_before_discount'): <span class="line-through">{{ number_format($package->old_price) }}</span>
                            </span>
                        @endif
                        <span class="text-sage-700 font-bold text-lg">
                            {{ number_format($package->price) }}
                            <span class="text-xs font-normal">@lang('site.currency_sar')</span>
                        </span>
                    </div>
                    <a href="{{ route('clinic.book.form', $clinic->slug) }}"
                       data-track="booking" data-clinic="{{ $clinic->id }}"
                       class="bg-sage-600 hover:bg-sage-700 text-white text-sm px-4 py-2 rounded-lg font-semibold transition-colors">
                        @lang('site.book_appointment')
                    </a>
                </div>
                @if($package->expires_at)
                    <p class="mt-2 text-xs text-amber-600">{{ __('site.offer_until', ['date' => $package->expires_at->translatedFormat('d M Y')]) }}</p>
                @endif
            </div>
        @endforeach
    </div>
@endif
