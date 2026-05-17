{{-- Account sidebar nav (used in all account pages) --}}
<aside class="bg-white rounded-xl shadow-sm p-4 sticky top-20">
    <nav class="flex flex-col gap-1 text-sm">
        @php
            $items = [
                ['route' => 'account.show',      'label' => 'site.account_profile',      'icon' => '👤'],
                ['route' => 'account.bookings',  'label' => 'site.account_my_bookings',  'icon' => '📅'],
                ['route' => 'account.favorites', 'label' => 'site.account_my_favorites', 'icon' => '⭐'],
            ];
        @endphp
        @foreach($items as $item)
            <a href="{{ route($item['route']) }}"
               class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs($item['route']) ? 'bg-teal-50 text-teal-700 font-semibold' : 'text-gray-600 hover:bg-gray-50' }}">
                <span>{{ $item['icon'] }}</span>
                <span>@lang($item['label'])</span>
            </a>
        @endforeach

        <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 mt-2 pt-2">
            @csrf
            <button type="submit" class="w-full text-start flex items-center gap-2 px-3 py-2 rounded-lg text-red-600 hover:bg-red-50 transition-colors">
                <span>🚪</span>
                <span>@lang('site.account_logout')</span>
            </button>
        </form>
    </nav>
</aside>
