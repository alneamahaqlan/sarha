@extends('layouts.public')

@section('title', __('site.cart_title'))

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">

    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold text-charcoal flex items-center gap-2">
            <x-icon name="shopping-bag" class="w-6 h-6 text-sage-600" />
            @lang('site.cart_title')
        </h1>
        <p class="text-sm text-slate-warm mt-1">@lang('site.cart_subtitle')</p>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm mb-4">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @if($groups->isEmpty())
        <div class="bg-white rounded-2xl shadow-soft p-12 text-center">
            <x-icon name="shopping-bag" class="w-16 h-16 mx-auto mb-4 text-gray-300" />
            <p class="text-gray-600 mb-4">@lang('site.cart_empty')</p>
            <a href="{{ route('search') }}" class="bg-sage-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors inline-block">
                @lang('site.account_start_browsing')
            </a>
        </div>
    @else
        <div class="space-y-6">
            @foreach($groups as $group)
                @php $clinic = $group['clinic']; @endphp
                <div class="bg-white rounded-2xl shadow-soft overflow-hidden">
                    {{-- Clinic header --}}
                    <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-gray-100 bg-gray-50/50">
                        <a href="{{ route('clinic.show', $clinic->slug) }}" class="font-semibold text-charcoal hover:text-sage-600">
                            {{ $clinic->name }}
                        </a>
                        @unless($group['active'])
                            <span class="text-xs bg-amber-50 text-amber-700 px-2.5 py-1 rounded-full">@lang('site.cart_clinic_paused')</span>
                        @endunless
                    </div>

                    <ul class="divide-y divide-gray-100">
                        @foreach($group['rows'] as $row)
                            @php $item = $row['item']; @endphp
                            <li class="flex items-center gap-3 px-5 py-4">
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-charcoal truncate">
                                        {{ $row['name'] }}
                                        @if($row['deleted'])
                                            <span class="text-xs text-red-500 ms-1">(@lang('site.cart_deleted'))</span>
                                        @endif
                                    </p>
                                    @if(! is_null($row['price']))
                                        <p class="text-sm font-semibold text-sage-700 mt-0.5">
                                            @if($row['priceFrom'])<span class="text-[10px] font-normal text-gray-500">@lang('site.price_from')</span>@endif
                                            {{ number_format((float) $row['price']) }} <x-riyal />
                                        </p>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    {{-- Book now (per item, per type). Hidden for deleted targets or a paused clinic. --}}
                                    @if($row['book'] && $group['active'])
                                        <a href="{{ $row['book']['href'] }}"
                                           @if($row['book']['newTab']) target="_blank" rel="noopener" @endif
                                           @if($row['book']['mode'] === 'contact')
                                               data-track="contact" data-clinic="{{ $clinic->id }}"
                                           @else
                                               data-track="booking" data-clinic="{{ $clinic->id }}"
                                           @endif
                                           class="inline-flex items-center justify-center gap-1.5 min-h-touch bg-sage-600 hover:bg-sage-700 text-white text-sm font-semibold px-4 rounded-lg transition-colors">
                                            <x-icon name="{{ $row['book']['mode'] === 'contact' ? 'phone' : 'calendar' }}" class="w-4 h-4" />
                                            {{ $row['book']['mode'] === 'contact' ? __('site.contact_for_inquiry') : __('site.book_now_label') }}
                                        </a>
                                    @endif

                                    {{-- Remove from cart --}}
                                    <form method="POST" action="{{ route('cart.remove', $item->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                title="@lang('site.cart_remove_item')" aria-label="@lang('site.cart_remove_item')"
                                                class="inline-flex items-center justify-center w-9 h-9 rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                            <x-icon name="trash" class="w-4 h-4" />
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
