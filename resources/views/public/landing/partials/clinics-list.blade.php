{{-- Clinic grid for city / specialty landing pages. --}}
<section class="max-w-6xl mx-auto px-4 py-12">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($listClinics as $c)
            <a href="{{ route('clinic.show', $c->slug) }}" class="group bg-white rounded-xl ring-1 ring-gray-100 hover:shadow-lg hover:ring-sage-200 transition-all p-5 flex gap-4 items-start">
                @if($c->logo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($c->logo) }}" alt="{{ $c->name }}" loading="lazy" class="h-14 w-14 rounded-lg object-cover flex-shrink-0">
                @else
                    <span class="h-14 w-14 rounded-lg bg-sage-50 text-sage-600 flex items-center justify-center flex-shrink-0 font-bold">{{ mb_substr($c->name, 0, 1) }}</span>
                @endif
                <div class="min-w-0">
                    <h3 class="font-bold text-gray-800 group-hover:text-sage-700 transition-colors line-clamp-1">{{ $c->name }}</h3>
                    @if($c->city)
                        <p class="text-sm text-gray-500 mt-0.5">{{ $c->city->name }}</p>
                    @endif
                    @if(($c->google_reviews_count ?? 0) > 0)
                        <p class="text-xs text-amber-600 mt-1">★ {{ number_format((float) $c->google_reviews_avg_rating, 1) }} ({{ $c->google_reviews_count }})</p>
                    @endif
                </div>
            </a>
        @endforeach
    </div>
</section>
