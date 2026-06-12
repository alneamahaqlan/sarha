{{-- Categories grid — "Explore by specialty". $data['categories'] limited by section. --}}
@php $categories = $data['categories'] ?? collect(); @endphp
@if($categories->isNotEmpty())
<section class="py-20 md:py-28 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-12">
            <p class="reveal text-sm font-semibold tracking-widest text-gold-deep uppercase mb-2">@lang('site.categories_eyebrow')</p>
            <h2 class="reveal font-display text-3xl font-bold text-charcoal" style="--reveal-delay:80ms">
                {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.browse_categories')) }}
            </h2>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-5">
            @foreach($categories as $i => $category)
                <a href="{{ route('search', ['category' => $category->id]) }}"
                   class="reveal reveal-zoom group relative flex flex-col items-start justify-between p-5 md:p-6 rounded-3xl bg-gradient-to-br {{ $i % 2 === 0 ? 'from-sage-mist/70 to-white' : 'from-gold-whisper/70 to-white' }} shadow-soft hover:shadow-soft-lg hover:-translate-y-1.5 transition-all duration-300 overflow-hidden min-h-[9rem]"
                   style="--reveal-delay:{{ ($i % 4) * 70 }}ms">
                    {{-- Floating glow that intensifies on hover. --}}
                    <span class="absolute -top-8 -end-8 w-24 h-24 rounded-full {{ $i % 2 === 0 ? 'bg-sage-primary/10' : 'bg-gold-primary/15' }} blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></span>
                    <span class="relative mb-4 w-14 h-14 rounded-2xl bg-white shadow-soft text-sage-primary flex items-center justify-center group-hover:scale-110 group-hover:text-gold-deep transition-all duration-300">
                        <x-category-icon :emoji="$category->emoji" :icon="$category->icon" class="w-7 h-7" />
                    </span>
                    <div class="relative">
                        <span class="block font-display font-bold text-charcoal group-hover:text-sage-deep transition-colors">{{ $category->display_name }}</span>
                        @if(! is_null($category->clinics_count ?? null) && $category->clinics_count > 0)
                            <span class="mt-1 inline-block text-xs font-medium text-slate-warm">{{ __('site.clinics_count', ['count' => $category->clinics_count]) }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
