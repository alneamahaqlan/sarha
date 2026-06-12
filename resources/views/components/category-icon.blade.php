@props(['emoji' => null, 'icon' => null])
@php
    use App\Support\CategoryIcons;
    use Illuminate\Support\Facades\Storage;

    // Resolution order for a category's visual:
    //   1. icon = uploaded image path  → render that image
    //   2. icon = a built-in icon key  → render its vector
    //   3. fall back to mapping the legacy emoji onto a built-in key
    $icon = trim((string) $icon);
    $isUploaded = $icon !== '' && ! CategoryIcons::has($icon);
    $key = CategoryIcons::has($icon) ? $icon : CategoryIcons::keyForEmoji($emoji);
@endphp
@if($isUploaded)
    <img src="{{ Storage::url($icon) }}" {{ $attributes->merge(['class' => 'w-5 h-5 object-contain']) }} alt="" aria-hidden="true">
@else
    <svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">{!! CategoryIcons::PATHS[$key] !!}</svg>
@endif
