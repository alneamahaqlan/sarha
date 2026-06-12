{{--
    Interactive before/after comparison slider.

    Progressive enhancement: resources/js/before-after-slider.js enhances any
    [data-ba-slider] (mouse + touch + keyboard, RTL-aware). Until JS runs the
    "before" image shows clipped to ~50% via CSS, so it degrades gracefully.

    Expects:
      $before       : "before" image URL
      $after        : "after"  image URL
      $heightClass  : Tailwind sizing for the slider box (e.g. 'h-44 w-full')
      $beforeLabel  : optional label text (default site.before)
      $afterLabel   : optional label text (default site.after)
--}}
@php
    $beforeLabel = $beforeLabel ?? __('site.before');
    $afterLabel  = $afterLabel ?? __('site.after');
    $heightClass = $heightClass ?? 'h-64 w-full';
@endphp
<div class="ba-slider {{ $heightClass }}" data-ba-slider>
    {{-- "after" is the full base layer; "before" is clipped on top from the inline-start edge --}}
    <img src="{{ $after }}" alt="{{ $afterLabel }}" loading="lazy" draggable="false" class="ba-slider__img ba-slider__img--after">
    <img src="{{ $before }}" alt="{{ $beforeLabel }}" loading="lazy" draggable="false" class="ba-slider__img ba-slider__img--before">

    <span class="ba-slider__label ba-slider__label--before">{{ $beforeLabel }}</span>
    <span class="ba-slider__label ba-slider__label--after">{{ $afterLabel }}</span>

    <div class="ba-slider__divider" data-ba-divider>
        <button type="button" class="ba-slider__handle" data-ba-handle
                role="slider" aria-orientation="horizontal"
                aria-valuemin="0" aria-valuemax="100" aria-valuenow="50"
                aria-label="{{ __('site.before_after_compare') }}">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="13 7 8 12 13 17"></polyline>
                <polyline points="16 7 21 12 16 17"></polyline>
            </svg>
        </button>
    </div>
</div>
