{{--
    Reusable "add to comparison" toggle. Drop next to any comparable card:
        <x-compare-toggle type="service" :id="$service->id" :name="$service->name" class="absolute top-1 end-1" />
        <x-compare-toggle type="offer"   :id="$offer->id"   :name="$offer->title" />
        <x-compare-toggle type="package" :id="$package->id" :name="$package->name" />
        <x-compare-toggle type="clinic"  :id="$clinic->id"  :name="$clinic->name" />

    Selection is purely client-side: the global tray script in the layout reads
    the data-* attributes, keeps one localStorage bucket PER type, and flips the
    `.is-selected` class on this button. Same type only ever compares with same
    type — buckets never mix.

    Props:
      type  — 'clinic' | 'service' | 'offer' | 'package'
      id    — the model key
      name  — label shown in the tray
      class — extra positioning classes (e.g. absolute placement)
--}}
@props(['type', 'id', 'name', 'class' => ''])

<button type="button"
        data-compare-type="{{ $type }}"
        data-compare-id="{{ $id }}"
        data-compare-name="{{ $name }}"
        aria-pressed="false"
        title="{{ __('site.compare_add') }}"
        aria-label="{{ __('site.compare_add') }}"
        class="compare-toggle z-20 inline-flex items-center justify-center w-9 h-9 rounded-full bg-white/95 text-gray-400 shadow-sm ring-1 ring-gray-100 transition-colors hover:text-sage-600 hover:bg-sage-50 [&.is-selected]:bg-sage-600 [&.is-selected]:text-white [&.is-selected]:ring-sage-600 {{ $class }}">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4"/>
    </svg>
</button>
