{{--
    Before/after gallery tab. Expects: $clinic with beforeAfterPhotos loaded.
--}}
@if($clinic->beforeAfterPhotos->isEmpty())
    <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
        @lang('site.no_before_after')
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach($clinic->beforeAfterPhotos as $photo)
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="grid grid-cols-2">
                    <div class="relative">
                        <img src="{{ Storage::url($photo->before_image) }}" alt="@lang('site.before')" loading="lazy" class="h-44 w-full object-cover">
                        <span class="absolute top-2 start-2 bg-black/60 text-white text-xs px-2 py-0.5 rounded">@lang('site.before')</span>
                    </div>
                    <div class="relative">
                        <img src="{{ Storage::url($photo->after_image) }}" alt="@lang('site.after')" loading="lazy" class="h-44 w-full object-cover">
                        <span class="absolute top-2 start-2 bg-teal-600/85 text-white text-xs px-2 py-0.5 rounded">@lang('site.after')</span>
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
                                <span class="bg-teal-50 text-teal-700 text-xs px-2 py-0.5 rounded-full">{{ $photo->service->name }}</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
