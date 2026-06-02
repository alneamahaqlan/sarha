{{--
    Heart "save to favourites" button for a service or an offer. Drop in
    anywhere a service/offer card renders:
        <x-save-button :model="$offer" type="offer" />
        <x-save-button :model="$service" type="service" />

    Guests get a link to the login page; signed-in customers get a form
    that POSTs to saved.toggle and reloads with the new state.

    Props:
      model — the Service or Offer Eloquent model
      type  — 'service' | 'offer'
      class — extra positioning classes (e.g. absolute placement)
--}}
@props(['model', 'type', 'class' => ''])

@php
    $saved = auth('web')->check() && auth('web')->user()->hasSaved($model);
    $base  = 'inline-flex items-center justify-center w-9 h-9 rounded-full shadow-sm ring-1 ring-gray-100 transition-colors '
        . ($saved ? 'bg-red-50 text-red-500 hover:bg-red-100' : 'bg-white/95 text-gray-400 hover:text-red-500 hover:bg-red-50');
@endphp

@auth('web')
    <form method="POST" action="{{ route('saved.toggle') }}" class="{{ $class }}">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="id" value="{{ $model->getKey() }}">
        <button type="submit"
                title="{{ $saved ? __('site.saved_remove') : __('site.saved_add') }}"
                aria-label="{{ $saved ? __('site.saved_remove') : __('site.saved_add') }}"
                class="{{ $base }}">
            <x-icon :name="$saved ? 'heart-solid' : 'heart'" class="w-4 h-4" />
        </button>
    </form>
@else
    <a href="{{ route('login') }}"
       title="{{ __('site.saved_login_prompt') }}"
       aria-label="{{ __('site.saved_login_prompt') }}"
       class="{{ $base }} {{ $class }}">
        <x-icon name="heart" class="w-4 h-4" />
    </a>
@endauth
