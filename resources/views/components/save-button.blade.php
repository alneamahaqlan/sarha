{{--
    Heart "save to favourites" button for a service or an offer. Drop in
    anywhere a service/offer card renders:
        <x-save-button :model="$offer" type="offer" />
        <x-save-button :model="$service" type="service" />

    Guests get a link to the login page; signed-in customers get a form
    that POSTs to saved.toggle. With JS on, favorites.js intercepts the
    submit and flips the heart in place (no reload); without JS the form
    still works and the controller redirects back().

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
    <form method="POST" action="{{ route('saved.toggle') }}" class="js-save-form {{ $class }}">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="id" value="{{ $model->getKey() }}">
        <button type="submit"
                data-fav-toggle
                data-saved="{{ $saved ? '1' : '0' }}"
                data-class-on="bg-red-50 text-red-500 hover:bg-red-100"
                data-class-off="bg-white/95 text-gray-400 hover:text-red-500 hover:bg-red-50"
                data-title-on="{{ __('site.saved_remove') }}"
                data-title-off="{{ __('site.saved_add') }}"
                title="{{ $saved ? __('site.saved_remove') : __('site.saved_add') }}"
                aria-label="{{ $saved ? __('site.saved_remove') : __('site.saved_add') }}"
                class="{{ $base }}">
            <span data-fav-icon-on class="{{ $saved ? '' : 'hidden' }}"><x-icon name="heart-solid" class="w-4 h-4" /></span>
            <span data-fav-icon-off class="{{ $saved ? 'hidden' : '' }}"><x-icon name="heart" class="w-4 h-4" /></span>
        </button>
    </form>
@else
    <a href="{{ route('login', ['redirect' => url()->current()]) }}"
       title="{{ __('site.saved_login_prompt') }}"
       aria-label="{{ __('site.saved_login_prompt') }}"
       class="{{ $base }} {{ $class }}">
        <x-icon name="heart" class="w-4 h-4" />
    </a>
@endauth
