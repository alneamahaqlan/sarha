@extends('layouts.public')

@section('title', __('site.account_my_favorites'))

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">@lang('site.account_my_favorites')</h1>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1">@include('public.account._nav')</div>

        <div class="lg:col-span-3">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm mb-5">
                    {{ session('success') }}
                </div>
            @endif

            @if($favorites->isEmpty())
                <div class="bg-white rounded-xl shadow-sm p-10 text-center">
                    <x-icon name="star" class="w-16 h-16 mx-auto mb-3 text-gray-300" />
                    <p class="text-gray-500 mb-4">@lang('site.account_no_favorites')</p>
                    <a href="{{ route('search') }}" class="bg-teal-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-teal-700 transition-colors inline-block">
                        @lang('site.account_start_browsing')
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($favorites as $clinic)
                        <div class="relative">
                            @include('public.partials.clinic-card', ['clinic' => $clinic])
                            <form method="POST" action="{{ route('favorites.toggle', $clinic->slug) }}"
                                  class="absolute top-3 end-3 z-20">
                                @csrf
                                <button type="submit"
                                        title="{{ __('site.favorite_remove') }}"
                                        class="bg-white/95 hover:bg-red-50 text-red-500 w-9 h-9 rounded-full flex items-center justify-center shadow transition-colors">
                                    <x-icon name="heart-solid" class="w-4 h-4" />
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>

                @if($favorites->hasPages())
                    <div class="mt-6">{{ $favorites->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
