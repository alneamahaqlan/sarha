@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $dir = $isRtl ? 'rtl' : 'ltr';
    $altLocale = $isRtl ? 'en' : 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('site.brand')) — @lang('site.tagline')</title>
    <meta name="description" content="@yield('description', __('site.meta_description'))">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    @if($isRtl)
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @else
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        html[lang="ar"] body { font-family: 'Cairo', ui-sans-serif, system-ui, sans-serif; }
        html[lang="en"] body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
    </style>

    @stack('head')
</head>
<body class="bg-gray-50 antialiased">

{{-- Navigation --}}
<nav class="bg-white shadow-sm sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <span class="text-2xl font-bold text-teal-600">@lang('site.brand')</span>
            </a>

            <div class="hidden md:flex items-center gap-6">
                <a href="{{ route('search') }}" class="text-gray-600 hover:text-teal-600 transition-colors">@lang('site.nav_search')</a>
            </div>

            <div class="flex items-center gap-3">
                {{-- Language switcher --}}
                <a href="{{ route('lang.switch', $altLocale) }}"
                   class="text-sm text-gray-600 hover:text-teal-600 transition-colors px-2 py-1 border border-gray-200 rounded-lg"
                   title="@lang('site.language')">
                    {{ $altLocale === 'en' ? 'English' : 'العربية' }}
                </a>

                @auth('web')
                    <a href="{{ route('account.show') }}"
                       class="text-sm text-gray-700 hover:text-teal-600 transition-colors flex items-center gap-1.5">
                        <span class="w-7 h-7 rounded-full bg-teal-100 text-teal-700 flex items-center justify-center text-xs font-bold">
                            {{ mb_substr(auth('web')->user()->name ?: 'م', 0, 1) }}
                        </span>
                        <span class="hidden sm:inline">{{ auth('web')->user()->name ?: __('site.account_title') }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-gray-500 hover:text-red-500">@lang('site.nav_logout')</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="bg-teal-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-teal-700 transition-colors">
                        @lang('site.nav_login')
                    </a>
                @endauth
            </div>
        </div>
    </div>
</nav>

{{-- Flash messages --}}
@if(session('success'))
    <div class="bg-green-50 border-b border-green-200 text-green-800 px-4 py-3 text-center text-sm">
        {{ session('success') }}
    </div>
@endif

@yield('content')

<footer class="bg-gray-900 text-gray-400 mt-16">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
                <h3 class="text-white text-xl font-bold mb-3">@lang('site.brand')</h3>
                <p class="text-sm leading-relaxed">@lang('site.footer_about')</p>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-3">@lang('site.footer_quick_links')</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('home') }}" class="hover:text-white transition-colors">@lang('site.breadcrumb_home')</a></li>
                    <li><a href="{{ route('search') }}" class="hover:text-white transition-colors">@lang('site.nav_search')</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-3">@lang('site.footer_for_clinics')</h4>
                <p class="text-sm mb-3">@lang('site.footer_for_clinics_desc')</p>
                <a href="#" class="bg-teal-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-teal-700 transition-colors inline-block">
                    @lang('site.nav_register_clinic')
                </a>
            </div>
        </div>
        <div class="border-t border-gray-800 mt-8 pt-6 text-center text-sm">
            {!! __('site.footer_rights', ['year' => date('Y')]) !!}
        </div>
    </div>
</footer>

</body>
</html>
