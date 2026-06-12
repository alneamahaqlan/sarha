{{--
    Instagram-style story viewer overlay. Lives inside an Alpine scope created
    by storyViewer($items) on an ancestor (the hero card). Reads: open, items,
    index, progress; calls: show(), next(), prev(), close().
    Expects: $clinic (for the header logo + name).
--}}
<div x-show="open" x-cloak
     class="fixed inset-0 z-[60] bg-black/90 flex items-center justify-center"
     @keydown.escape.window="close()">

    <div class="relative w-full h-full sm:h-[92vh] sm:max-w-md bg-black sm:rounded-2xl overflow-hidden select-none">

        {{-- Segmented progress bars (one per story) --}}
        <div class="absolute top-0 inset-x-0 z-30 flex gap-1 p-3">
            <template x-for="(it, i) in items" :key="i">
                <div class="h-1 flex-1 rounded-full bg-white/30 overflow-hidden">
                    <div class="h-full bg-white"
                         :style="`width: ${ i < index ? 100 : (i === index ? progress : 0) }%`"></div>
                </div>
            </template>
        </div>

        {{-- Header: clinic avatar + name + close --}}
        <div class="absolute top-4 inset-x-0 z-30 flex items-center gap-2 px-4 pt-3">
            @if($clinic->logo)
                <img src="{{ Storage::url($clinic->logo) }}" alt="{{ $clinic->name }}"
                     class="w-9 h-9 rounded-full object-cover ring-2 ring-white/70">
            @else
                <span class="w-9 h-9 rounded-full bg-white/20 text-white flex items-center justify-center text-sm font-bold">{{ mb_substr($clinic->name, 0, 1) }}</span>
            @endif
            <span class="text-white text-sm font-semibold truncate">{{ $clinic->name }}</span>
            <button type="button" @click="close()" aria-label="@lang('site.close')"
                    class="ms-auto text-white/90 hover:text-white p-1">
                <x-icon name="x" class="w-6 h-6" />
            </button>
        </div>

        {{-- The story image --}}
        <img :src="items[index] && items[index].url" alt=""
             class="absolute inset-0 w-full h-full object-contain">

        {{-- Caption --}}
        <template x-if="items[index] && items[index].caption">
            <div class="absolute bottom-0 inset-x-0 z-20 p-5 pt-12 bg-gradient-to-t from-black/80 to-transparent">
                <p class="text-white text-sm leading-relaxed text-center" x-text="items[index].caption"></p>
            </div>
        </template>

        {{-- Tap zones: start (right in RTL) = previous, end (left) = next --}}
        <button type="button" @click="prev()" aria-label="@lang('site.previous')"
                class="absolute inset-y-0 start-0 z-10 w-1/3"></button>
        <button type="button" @click="next()" aria-label="@lang('site.next')"
                class="absolute inset-y-0 end-0 z-10 w-1/3"></button>
    </div>
</div>
