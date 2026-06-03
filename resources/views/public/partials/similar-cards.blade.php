{{--
    Shared "similar / related" strip — one component for every page that
    shows "more like this from other complexes". Fed by App\Services\SimilarityService.

    Expects:
      $items    Collection  — offers / sub-clinics / doctors / clinics
      $type     string      — 'offer' | 'subClinic' | 'doctor' | 'clinic'
      $title    string      — section heading (already translated)
      $subtitle ?string     — optional small caption under the heading

    Each card links to the relevant nested detail page; clicking an offer
    goes to the OFFER page (never straight to booking), per spec.
--}}
@php $subtitle = $subtitle ?? null; @endphp

@if($items->isNotEmpty())
    <section class="mt-12">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-gray-800">{{ $title }}</h2>
            @if($subtitle)
                <p class="text-sm text-gray-500 mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>

        {{-- Horizontal scroll on mobile, wrapping grid feel on desktop. --}}
        <div class="flex gap-4 overflow-x-auto pb-2 -mx-1 px-1 snap-x">
            @foreach($items as $item)
                @php
                    $cardClinic = $item->clinic ?? null;
                    [$href, $imageUrl, $heading, $caption, $cityName] = match ($type) {
                        'offer' => [
                            route('offer.show', ['slug' => $cardClinic?->slug, 'offer' => $item->id]),
                            $item->effectiveImage() ? \Illuminate\Support\Facades\Storage::url($item->effectiveImage()) : null,
                            $item->title,
                            $item->service?->name,
                            $cardClinic?->city?->display_name,
                        ],
                        'subClinic' => [
                            route('subclinic.show', ['slug' => $cardClinic?->slug, 'subClinic' => $item->id]),
                            null,
                            $item->display_name,
                            trim(($item->category?->emoji ? $item->category->emoji . ' ' : '') . ($item->category?->name ?? '')),
                            $cardClinic?->city?->display_name,
                        ],
                        'service' => [
                            route('service.show', ['slug' => $cardClinic?->slug, 'service' => $item->id]),
                            $item->image ? \Illuminate\Support\Facades\Storage::url($item->image) : null,
                            $item->name,
                            $item->categories?->first()?->name,
                            $cardClinic?->city?->display_name,
                        ],
                        'doctor' => [
                            route('doctor.show', ['slug' => $cardClinic?->slug, 'doctor' => $item->id]),
                            $item->photo_url,
                            $item->name,
                            $item->specialty,
                            $cardClinic?->city?->display_name,
                        ],
                        'clinic' => [
                            route('clinic.show', ['slug' => $item->slug]),
                            $item->logo ? \Illuminate\Support\Facades\Storage::url($item->logo) : null,
                            $item->name,
                            null,
                            $item->city?->display_name,
                        ],
                        default => ['#', null, '', null, null],
                    };
                    $discount = $type === 'offer' ? $item->discountPercentage() : null;
                @endphp

                <a href="{{ $href }}"
                   class="group shrink-0 w-44 snap-start bg-white rounded-xl shadow-sm ring-1 ring-gray-100 hover:shadow-lg transition-all overflow-hidden flex flex-col">
                    <div class="relative aspect-[4/3] bg-gradient-to-br from-sage-mist to-gold-whisper flex items-center justify-center text-3xl">
                        @if($imageUrl)
                            <img src="{{ $imageUrl }}" alt="{{ $heading }}" loading="lazy"
                                 class="absolute inset-0 w-full h-full object-cover">
                        @else
                            <x-icon name="{{ $type === 'doctor' ? 'user' : (in_array($type, ['clinic', 'subClinic']) ? 'building' : 'star') }}" class="w-8 h-8 text-sage-400" />
                        @endif
                        @if($discount)
                            <span class="absolute top-2 start-2 bg-red-500 text-white text-[11px] font-bold px-2 py-0.5 rounded-full shadow">
                                -{{ $discount }}%
                            </span>
                        @endif
                    </div>

                    <div class="p-3 flex-1 flex flex-col">
                        <h3 class="text-sm font-bold text-gray-800 line-clamp-2 group-hover:text-sage-700 transition-colors">{{ $heading }}</h3>

                        @if($caption)
                            <p class="text-xs text-gray-500 mt-1 line-clamp-1">{{ $caption }}</p>
                        @endif

                        @if(in_array($type, ['offer', 'service'], true) && $item->price !== null)
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-sage-700 font-bold text-sm">
                                    {{ number_format((float) $item->price) }}
                                    <span class="text-[10px] font-normal">@lang('site.currency_sar')</span>
                                </span>
                                @if($item->old_price !== null)
                                    <span class="text-xs text-gray-400 line-through">{{ number_format((float) $item->old_price) }}</span>
                                @endif
                            </div>
                        @endif

                        <div class="mt-auto pt-2 text-[11px] text-gray-400 line-clamp-1">
                            @if($cardClinic)
                                {{ $cardClinic->name }}@if($cityName) · {{ $cityName }}@endif
                            @elseif($cityName)
                                {{ $cityName }}
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
