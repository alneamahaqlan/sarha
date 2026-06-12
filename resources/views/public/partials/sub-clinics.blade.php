{{--
    Services tab body — nested view: complex → clinic (sub-clinic) → services.
    Expects: $clinic with relations: subClinics.category, subClinics.services, services.
    Browse-mode only; the homepage offer cards link directly to the booking
    form, so there's no need to filter this view to a single service.
--}}
@php
    // Services that don't belong to any sub-clinic ("general services" bucket).
    $generalServices = $clinic->services->whereNull('sub_clinic_id');
    $spec = $spec ?? false;
@endphp

@if($clinic->subClinics->isEmpty() && $generalServices->isEmpty())
    <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
        @lang('site.no_services_yet')
    </div>
@else
    {{-- One section per sub-clinic, with its services nested inside. --}}
    @foreach($clinic->subClinics as $sub)
        @php
            // Block shows when the sub-clinic's own specialty OR any of its
            // services' specialties match the active filter.
            $subFallback = $sub->category_id ? [$sub->category_id] : [];
            $subCats = collect($subFallback)
                ->merge($sub->services->flatMap(fn ($s) => $s->relationLoaded('categories') ? $s->categories->pluck('id') : []))
                ->unique()->values()->all();
        @endphp
        <div class="bg-white rounded-xl shadow-sm p-6"
             @if($spec) x-show="$store.spec.show(@js($subCats))" @endif>
            <div class="flex items-start justify-between gap-3 mb-1">
                <div class="min-w-0">
                    <a href="{{ route('subclinic.show', ['slug' => $clinic->slug, 'subClinic' => $sub->id]) }}"
                       class="group inline-flex items-center gap-1.5">
                        <h2 class="text-lg font-bold text-gray-800 group-hover:text-sage-700 transition-colors">{{ $sub->display_name }}</h2>
                        <x-icon name="search" class="w-0 h-4 opacity-0 group-hover:w-4 group-hover:opacity-60 transition-all rtl:rotate-90" />
                    </a>
                    @if($sub->category)
                        <p class="inline-flex items-center gap-1 text-xs text-gray-500 mt-0.5">
                            <x-category-icon :emoji="$sub->category->emoji" :icon="$sub->category->icon" class="w-3.5 h-3.5" /> {{ $sub->category->display_name }}
                        </p>
                    @endif
                </div>
                <a href="{{ route('subclinic.show', ['slug' => $clinic->slug, 'subClinic' => $sub->id]) }}"
                   class="text-xs font-semibold text-sage-600 hover:text-sage-700 whitespace-nowrap flex-shrink-0 inline-flex items-center gap-1">
                    @lang('site.detail_subclinic_title') <span class="rtl:rotate-180">→</span>
                </a>
            </div>

            @if($sub->description)
                <p class="text-sm text-gray-600 leading-relaxed mb-4">{{ $sub->description }}</p>
            @endif

            @if($sub->services->isEmpty())
                <p class="text-sm text-gray-400 py-4 text-center">@lang('site.no_services_in_sub_clinic')</p>
            @else
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 border-t border-gray-100 pt-4 mt-2">
                    @foreach($sub->services as $service)
                        @include('public.partials.service-row', ['service' => $service, 'clinic' => $clinic, 'spec' => $spec, 'fallbackCats' => $subFallback])
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    {{-- General services (no sub_clinic assigned). --}}
    @if($generalServices->isNotEmpty())
        @php
            $genCats = $generalServices
                ->flatMap(fn ($s) => $s->relationLoaded('categories') ? $s->categories->pluck('id') : [])
                ->unique()->values()->all();
        @endphp
        <div class="bg-white rounded-xl shadow-sm p-6"
             @if($spec) x-show="$store.spec.show(@js($genCats))" @endif>
            <div class="flex items-start justify-between gap-3 mb-3">
                <h2 class="text-lg font-bold text-gray-800">@lang('site.general_services')</h2>
                <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0">
                    {{ __('site.services_count', ['count' => $generalServices->count()]) }}
                </span>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 border-t border-gray-100 pt-4">
                @foreach($generalServices as $service)
                    @include('public.partials.service-row', ['service' => $service, 'clinic' => $clinic, 'spec' => $spec])
                @endforeach
            </div>
        </div>
    @endif
@endif
