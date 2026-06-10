{{--
    Before/after gallery tab. Expects: $clinic with beforeAfterPhotos loaded.
    Each case renders either side-by-side (default) or as an interactive
    drag-to-compare slider, per its display_mode.
--}}
@if($clinic->beforeAfterPhotos->isEmpty())
    <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
        @lang('site.no_before_after')
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach($clinic->beforeAfterPhotos as $photo)
            @php $detailUrl = route('before-after.show', ['slug' => $clinic->slug, 'photo' => $photo->id]); @endphp

            @if($photo->display_mode === 'slider')
                {{-- Interactive slider variant: the case becomes a drag-to-compare component. --}}
                <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all overflow-hidden">
                    @include('public.partials.before-after-slider', [
                        'before'      => Storage::url($photo->before_image),
                        'after'       => Storage::url($photo->after_image),
                        'heightClass' => 'h-44 w-full',
                    ])
                    <div class="p-3">
                        @if($photo->title)
                            <p class="font-medium text-gray-800">{{ $photo->title }}</p>
                        @endif
                        <div class="flex flex-wrap items-center gap-2 mt-1">
                            @if($photo->subClinic)
                                <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">{{ $photo->subClinic->display_name }}</span>
                            @endif
                            @if($photo->service)
                                <span class="bg-sage-50 text-sage-700 text-xs px-2 py-0.5 rounded-full">{{ $photo->service->name }}</span>
                            @endif
                            <a href="{{ $detailUrl }}" class="ms-auto text-xs text-sage-600 hover:text-sage-700 hover:underline">@lang('site.before_after_view')</a>
                        </div>
                    </div>
                </div>
            @else
                {{-- Default: two images side by side (whole card links to the detail page). --}}
                <a href="{{ $detailUrl }}"
                   class="group block bg-white rounded-xl shadow-sm hover:shadow-md transition-all overflow-hidden">
                    <div class="grid grid-cols-2">
                        <div class="relative">
                            <img src="{{ Storage::url($photo->before_image) }}" alt="@lang('site.before')" loading="lazy" class="h-44 w-full object-cover">
                            <span class="absolute top-2 start-2 bg-black/60 text-white text-xs px-2 py-0.5 rounded">@lang('site.before')</span>
                        </div>
                        <div class="relative">
                            <img src="{{ Storage::url($photo->after_image) }}" alt="@lang('site.after')" loading="lazy" class="h-44 w-full object-cover">
                            <span class="absolute top-2 start-2 bg-sage-600/85 text-white text-xs px-2 py-0.5 rounded">@lang('site.after')</span>
                        </div>
                    </div>
                    @if($photo->title || $photo->service || $photo->subClinic)
                        <div class="p-3">
                            @if($photo->title)
                                <p class="font-medium text-gray-800">{{ $photo->title }}</p>
                            @endif
                            <div class="flex flex-wrap gap-2 mt-1">
                                @if($photo->subClinic)
                                    <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">{{ $photo->subClinic->display_name }}</span>
                                @endif
                                @if($photo->service)
                                    <span class="bg-sage-50 text-sage-700 text-xs px-2 py-0.5 rounded-full">{{ $photo->service->name }}</span>
                                @endif
                            </div>
                        </div>
                    @endif
                </a>
            @endif
        @endforeach
    </div>
@endif
