{{-- Side-by-side comparison of the complexes attached to the page. --}}
<section class="max-w-6xl mx-auto px-4 py-12">
    <div class="overflow-x-auto">
        <div class="grid gap-4" style="grid-template-columns: repeat({{ $comparisonClinics->count() }}, minmax(220px, 1fr));">
            @foreach($comparisonClinics as $c)
                <div class="bg-white rounded-2xl ring-1 {{ $c->pivot->highlight ? 'ring-sage-400 shadow-lg' : 'ring-gray-100' }} p-5 flex flex-col">
                    @if($c->pivot->highlight)
                        <span class="self-start mb-2 text-xs font-semibold bg-sage-100 text-sage-700 px-2 py-0.5 rounded-full">@lang('site.lp_book_now')</span>
                    @endif
                    <div class="flex items-center gap-3">
                        @if($c->logo)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($c->logo) }}" alt="{{ $c->name }}" class="h-12 w-12 rounded-lg object-cover">
                        @endif
                        <h3 class="font-bold text-gray-800 line-clamp-2">{{ $c->name }}</h3>
                    </div>

                    <dl class="mt-4 space-y-2 text-sm flex-1">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">@lang('site.booking_target_city')</dt>
                            <dd class="font-medium text-gray-800">{{ $c->city?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">@lang('site.reviews_block_title')</dt>
                            <dd class="font-medium text-amber-600">
                                @if(($c->google_reviews_count ?? 0) > 0)
                                    ★ {{ number_format((float) $c->google_reviews_avg_rating, 1) }} ({{ $c->google_reviews_count }})
                                @else — @endif
                            </dd>
                        </div>
                    </dl>

                    <a href="{{ route('clinic.show', $c->slug) }}"
                       class="mt-4 text-center bg-sage-600 text-white py-2.5 rounded-lg font-semibold hover:bg-sage-700 transition">
                        @lang('site.booking_continue_clinic')
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
