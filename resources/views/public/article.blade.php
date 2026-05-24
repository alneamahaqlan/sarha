@extends('layouts.public')

@section('title', $article->title)
@section('description', $article->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($article->body ?? ''), 155))
@section('og_type', 'article')
@section('og_title', $article->title)
@section('og_description', $article->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($article->body ?? ''), 155))
@section('og_image', $article->cover_image ? Storage::url($article->cover_image) : asset('images/og-default.png'))

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">

    {{-- Breadcrumb --}}
    <nav class="flex flex-wrap items-center gap-2 text-sm text-gray-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-teal-600">@lang('site.breadcrumb_home')</a>
        <span>/</span>
        <a href="{{ route('clinic.show', $article->clinic->slug) }}" class="hover:text-teal-600">{{ $article->clinic->name }}</a>
        <span>/</span>
        <span class="text-gray-700 truncate">{{ $article->title }}</span>
    </nav>

    <article class="bg-white rounded-2xl shadow-sm overflow-hidden">
        @if($article->cover_image)
            <img src="{{ Storage::url($article->cover_image) }}" alt="{{ $article->title }}"
                 class="w-full h-64 md:h-80 object-cover">
        @endif

        <div class="p-6 md:p-8">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3">{{ $article->title }}</h1>

            {{-- Meta row --}}
            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 mb-6 pb-6 border-b border-gray-100">
                <a href="{{ route('clinic.show', $article->clinic->slug) }}" class="flex items-center gap-1.5 hover:text-teal-600">
                    <x-icon name="building" class="w-4 h-4" /> <span class="font-medium">{{ $article->clinic->name }}</span>
                </a>
                @if($article->published_at ?? $article->created_at)
                    <span class="inline-flex items-center gap-1.5"><x-icon name="calendar" class="w-4 h-4" /> {{ ($article->published_at ?? $article->created_at)->translatedFormat('d M Y') }}</span>
                @endif
                <span class="inline-flex items-center gap-1.5"><x-icon name="eye" class="w-4 h-4" /> {{ __('site.article_views', ['count' => $article->views_count]) }}</span>
            </div>

            {{-- Body (rich HTML from the clinic's editor) --}}
            <div class="prose prose-teal max-w-none text-gray-700 leading-relaxed [&_h2]:font-bold [&_h2]:text-gray-800 [&_h2]:text-xl [&_h2]:mt-6 [&_h2]:mb-2 [&_h3]:font-semibold [&_h3]:text-gray-800 [&_h3]:mt-4 [&_ul]:list-disc [&_ul]:ps-5 [&_ol]:list-decimal [&_ol]:ps-5 [&_a]:text-teal-600 [&_a]:underline [&_p]:mb-3">
                {!! $article->body !!}
            </div>

            @if($article->tags)
                <div class="flex flex-wrap gap-2 mt-6">
                    @foreach($article->tags as $tag)
                        <span class="bg-teal-50 text-teal-600 text-xs px-2.5 py-1 rounded-full">#{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </article>

    {{-- Back to clinic --}}
    <div class="mt-6">
        <a href="{{ route('clinic.show', $article->clinic->slug) }}"
           class="inline-flex items-center gap-1.5 text-teal-600 hover:text-teal-700 font-medium">
            ← {{ $article->clinic->name }}
        </a>
    </div>

    {{-- Related articles from the same clinic --}}
    @if($related->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.related_articles')</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach($related as $r)
                    <a href="{{ route('article.show', $r->slug) }}"
                       class="block bg-white rounded-xl shadow-sm hover:shadow-md transition-all overflow-hidden">
                        @if($r->cover_image)
                            <img src="{{ Storage::url($r->cover_image) }}" alt="{{ $r->title }}" loading="lazy" class="w-full h-28 object-cover">
                        @endif
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-800 text-sm mb-1 line-clamp-2">{{ $r->title }}</h3>
                            <p class="text-xs text-gray-500 line-clamp-2">{{ \Illuminate\Support\Str::limit($r->meta_description ?: strip_tags($r->body ?? ''), 90) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
