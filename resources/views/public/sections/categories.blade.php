{{-- Categories grid — "Explore by specialty". $data['categories'] limited by section. --}}
@php $categories = $data['categories'] ?? collect(); @endphp
@if($categories->isNotEmpty())
<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            <p class="reveal text-sm font-semibold tracking-widest text-gold-deep uppercase mb-2">@lang('site.categories_eyebrow')</p>
            <h2 class="reveal font-display text-3xl font-bold text-charcoal" style="--reveal-delay:80ms">
                {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.browse_categories')) }}
            </h2>
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-7 gap-3 md:gap-4">
            @foreach($categories as $i => $category)
                <a href="{{ route('search', ['category' => $category->id]) }}"
                   class="reveal reveal-zoom group flex flex-col items-center justify-center p-4 bg-white rounded-2xl shadow-sm ring-1 ring-gray-100 hover:shadow-xl hover:ring-gold-soft hover:-translate-y-1 transition-all duration-300 text-center"
                   style="--reveal-delay:{{ ($i % 7) * 60 }}ms">
                    <span class="mb-2.5 w-12 h-12 rounded-xl bg-sage-mist text-sage-primary flex items-center justify-center group-hover:bg-gold-whisper group-hover:text-gold-deep transition-colors">
                        <x-category-icon :emoji="$category->emoji" class="w-6 h-6" />
                    </span>
                    <span class="text-xs font-medium text-gray-700 group-hover:text-sage-deep">{{ $category->display_name }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
