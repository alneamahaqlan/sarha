@extends('layouts.public')

@section('title', $page->title)
@section('description', $page->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($page->body ?? ''), 155))
@section('og_title', $page->title)
@section('og_description', $page->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($page->body ?? ''), 155))

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">

    {{-- Breadcrumb --}}
    <nav class="flex flex-wrap items-center gap-2 text-sm text-gray-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-sage-600">@lang('site.breadcrumb_home')</a>
        <span>/</span>
        <span class="text-gray-700 truncate">{{ $page->title }}</span>
    </nav>

    <article class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="p-6 md:p-8">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6 pb-6 border-b border-gray-100">{{ $page->title }}</h1>

            {{-- Body (rich HTML authored by the super-admin in the panel) --}}
            <div class="prose prose-teal max-w-none text-gray-700 leading-relaxed [&_h2]:font-bold [&_h2]:text-gray-800 [&_h2]:text-xl [&_h2]:mt-6 [&_h2]:mb-2 [&_h3]:font-semibold [&_h3]:text-gray-800 [&_h3]:mt-4 [&_ul]:list-disc [&_ul]:ps-5 [&_ol]:list-decimal [&_ol]:ps-5 [&_a]:text-sage-600 [&_a]:underline [&_p]:mb-3">
                {!! $page->body !!}
            </div>
        </div>
    </article>
</div>
@endsection
