@extends('layouts.public')

@section('title', __('site.blog_title'))
@section('description', __('site.blog_subtitle'))

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">

    {{-- Breadcrumb --}}
    <nav class="flex flex-wrap items-center gap-2 text-sm text-gray-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-sage-600">@lang('site.breadcrumb_home')</a>
        <span>/</span>
        <span class="text-gray-700">@lang('site.blog_title')</span>
    </nav>

    {{-- Heading --}}
    <div class="mb-8">
        <h1 class="font-display text-3xl font-bold text-charcoal">@lang('site.blog_title')</h1>
        <p class="text-gray-500 text-sm mt-1">@lang('site.blog_subtitle')</p>
    </div>

    @if($articles->isEmpty())
        <div class="bg-white rounded-2xl p-10 text-center text-gray-400 ring-1 ring-gray-100">
            @lang('site.home_no_articles')
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($articles as $article)
                <article class="bg-white rounded-2xl ring-1 ring-gray-100 hover:shadow-xl transition-shadow overflow-hidden h-full flex flex-col">
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
                                    <a href="{{ route('clinic.show', $article->clinic->slug) }}" class="inline-flex items-center gap-1 hover:text-sage-600">
                                        <x-icon name="building" class="w-3.5 h-3.5" />
                                        <span class="line-clamp-1">{{ $article->clinic->name }}</span>
                                    </a>
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

        <div class="mt-8">
            {{ $articles->links() }}
        </div>
    @endif
</div>
@endsection
