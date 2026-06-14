{{-- Entities carrying an admin-chosen badge (Badges Center).
     $data = ['targetType' => clinic|offer|service|doctor, 'items' => Collection, 'badge' => Badge].
     Self-hides when no entity currently holds the badge. --}}
@php
    $items = $data['items'] ?? collect();
    $targetType = $data['targetType'] ?? 'clinic';
    $badge = $data['badge'] ?? null;
    // Pre-warm offer badges in one query when this strip lists offers.
    if ($targetType === 'offer' && $items->isNotEmpty()) {
        app(\App\Services\ClinicBadgeService::class)->forTargets(\App\Models\Offer::class, $items->pluck('id')->all(), 'cards');
    }
@endphp
@if($items->isNotEmpty())
<section class="py-16 md:py-24 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-8">
            <div>
                @if($badge)
                    <div class="reveal mb-2">
                        @include('public.partials.badge-chip', ['badge' => [
                            'label' => $badge->label(),
                            'description' => $badge->description(),
                            'icon' => $badge->icon,
                            'color' => $badge->color,
                        ]])
                    </div>
                @endif
                <h2 class="reveal font-display text-3xl font-bold text-charcoal" style="--reveal-delay:80ms">
                    {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : ($badge?->label() ?? __('site.featured'))) }}
                </h2>
                @if($badge?->description())
                    <p class="reveal text-slate-warm text-sm mt-1" style="--reveal-delay:140ms">{{ $badge->description() }}</p>
                @endif
            </div>
        </div>

        @if($targetType === 'offer')
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                @foreach($items as $i => $offer)
                    <div class="reveal" style="--reveal-delay:{{ ($i % 4) * 70 }}ms">
                        @include('public.partials.offer-card', ['offer' => $offer, 'clinic' => $offer->clinic, 'large' => false])
                    </div>
                @endforeach
            </div>
        @elseif($targetType === 'service')
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                @foreach($items as $i => $service)
                    <a href="{{ route('service.show', ['slug' => $service->clinic->slug, 'service' => $service->id]) }}"
                       class="reveal bg-white rounded-3xl shadow-soft hover:shadow-soft-lg hover:-translate-y-1 transition-all duration-300 overflow-hidden flex flex-col p-4"
                       style="--reveal-delay:{{ ($i % 4) * 70 }}ms">
                        <h3 class="text-sm font-bold text-gray-800 line-clamp-2">{{ $service->name }}</h3>
                        <p class="text-[11px] text-gray-500 mt-1 line-clamp-1">{{ $service->clinic->name }}</p>
                        @if($service->price !== null)
                            <div class="mt-3 text-sage-700 font-bold text-base">
                                {{ number_format((float) $service->price) }} <span class="text-xs font-normal"><x-riyal /></span>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        @elseif($targetType === 'doctor')
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                @foreach($items as $i => $doctor)
                    <a href="{{ route('doctor.show', ['slug' => $doctor->clinic->slug, 'doctor' => $doctor->id]) }}"
                       class="reveal bg-white rounded-3xl shadow-soft hover:shadow-soft-lg hover:-translate-y-1 transition-all duration-300 overflow-hidden flex flex-col items-center text-center p-4"
                       style="--reveal-delay:{{ ($i % 4) * 70 }}ms">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-sage-mist to-gold-whisper flex items-center justify-center overflow-hidden mb-3">
                            @if($doctor->photo)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($doctor->photo) }}" alt="{{ $doctor->name }}" loading="lazy" class="w-full h-full object-cover">
                            @else
                                <x-icon name="user" class="w-8 h-8 text-sage-600/40" />
                            @endif
                        </div>
                        <h3 class="text-sm font-bold text-gray-800 line-clamp-1">{{ $doctor->name }}</h3>
                        @if($doctor->specialty)
                            <p class="text-[11px] text-gray-500 mt-0.5 line-clamp-1">{{ $doctor->specialty }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($items as $i => $clinic)
                    <div class="reveal" style="--reveal-delay:{{ ($i % 4) * 70 }}ms">
                        @include('public.partials.clinic-card', ['clinic' => $clinic])
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif
