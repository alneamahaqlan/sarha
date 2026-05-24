@extends('layouts.public')

@section('title', __('errors.404_title'))

@section('content')
<div class="max-w-2xl mx-auto px-4 py-16 text-center">
    <div class="bg-white rounded-2xl shadow-sm p-10">
        <x-icon name="search" class="w-20 h-20 mx-auto mb-4 text-gray-300" />
        <h1 class="text-3xl font-bold text-gray-900 mb-2">@lang('errors.404_title')</h1>
        <p class="text-gray-500 mb-8">@lang('errors.404_subtitle')</p>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('home') }}" class="bg-teal-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-teal-700 transition-colors">
                @lang('errors.404_back_home')
            </a>
            <a href="{{ route('search') }}" class="bg-white border border-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                @lang('errors.404_browse')
            </a>
        </div>
    </div>
</div>
@endsection
