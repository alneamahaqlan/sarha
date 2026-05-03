<a href="{{ route('clinic.show', $clinic->slug) }}"
   class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all overflow-hidden block group">
    @if($clinic->logo)
        <div class="h-40 overflow-hidden bg-gray-100">
            <img src="{{ Storage::url($clinic->logo) }}" alt="{{ $clinic->name }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
        </div>
    @else
        <div class="h-40 bg-gradient-to-br from-purple-100 to-purple-200 flex items-center justify-center">
            <span class="text-5xl">🏥</span>
        </div>
    @endif

    <div class="p-4">
        <div class="flex items-start justify-between mb-2">
            <h3 class="font-bold text-gray-800 group-hover:text-purple-600 transition-colors leading-snug">
                {{ $clinic->name }}
            </h3>
            @if($clinic->is_featured)
                <span class="bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5 rounded-full whitespace-nowrap ms-2">{{ __('site.featured') }} ⭐</span>
            @endif
        </div>

        <div class="flex items-center gap-1 text-gray-500 text-sm mb-3">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            {{ $clinic->city->display_name ?? '' }}
        </div>

        @if($clinic->categories->isNotEmpty())
            <div class="flex flex-wrap gap-1">
                @foreach($clinic->categories->take(3) as $cat)
                    <span class="bg-purple-50 text-purple-600 text-xs px-2 py-0.5 rounded-full">
                        {{ $cat->emoji ?? '' }} {{ $cat->display_name }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</a>
