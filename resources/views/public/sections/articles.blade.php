{{-- Latest published articles. $data['articles'] is a collection (with clinic loaded). --}}
@php $articles = $data['articles'] ?? collect(); @endphp
<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-8">
            <div>
                <p class="reveal text-sm font-semibold tracking-widest text-gold-deep uppercase mb-2">📚</p>
                <h2 class="reveal font-display text-3xl font-bold text-charcoal" style="--reveal-delay:80ms">
                    {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.home_articles_title')) }}
                </h2>
                <p class="reveal text-gray-500 text-sm mt-1" style="--reveal-delay:140ms">@lang('site.home_articles_subtitle')</p>
            </div>
            @if(\Illuminate\Support\Facades\Route::has('blog.index'))
                <a href="{{ route('blog.index') }}" class="reveal text-sage-600 text-sm font-semibold hover:text-sage-700 inline-flex items-center gap-1 whitespace-nowrap">
                    @lang('site.view_all') <span class="rtl:rotate-180">→</span>
                </a>
            @endif
        </div>

        @if($articles->isEmpty())
            <div class="bg-white rounded-2xl p-10 text-center text-gray-400 ring-1 ring-gray-100">
                @lang('site.home_no_articles')
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($articles as $i => $article)
                    <article class="reveal bg-white rounded-2xl ring-1 ring-gray-100 hover:shadow-xl transition-shadow overflow-hidden h-full flex flex-col" style="--reveal-delay:{{ ($i % 3) * 100 }}ms">
                        <a href="{{ route('article.show', $article->slug) }}" class="block aspect-[16/9] bg-gradient-to-br from-sage-mist to-gold-whisper relative">
                            @if($article->cover_image ?? false)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($article->cover_image) }}"
                                     alt="{{ $article->title }}"
                                     class="absolute inset-0 w-full h-full object-cover" loading="lazy">
                            @else
                                <span class="absolute inset-0 flex items-center justify-center text-5xl">📝</span>
                            @endif
                        </a>
                        <div class="p-5 flex-1 flex flex-col">
                            <a href="{{ route('article.show', $article->slug) }}" class="block">
                                <h3 class="font-display font-bold text-lg text-charcoal hover:text-sage-700 transition-colors line-clamp-2">{{ $article->title }}</h3>
                            </a>
                            @if($article->meta_description)
                                <p class="text-sm text-gray-500 mt-2 line-clamp-3 flex-1">{{ $article->meta_description }}</p>
                            @endif
                            <div class="mt-4 flex items-center justify-between text-xs text-gray-500">
                                <div>
                                    @if($article->clinic)
                                        <span class="inline-flex items-center gap-1">
                                            <x-icon name="building" class="w-3.5 h-3.5" />
                                            <span class="line-clamp-1">{{ $article->clinic->name }}</span>
                                        </span>
                                    @endif
                                </div>
                                @if($article->published_at)
                                    <time datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->translatedFormat('d M Y') }}</time>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
