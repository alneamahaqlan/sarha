{{--
    Services tab body — nested view: complex → clinic (sub-clinic) → services.
    Expects: $clinic with relations: subClinics.category, subClinics.services, services.
    Browse-mode only; the homepage offer cards link directly to the booking
    form, so there's no need to filter this view to a single service.
--}}
@php
    // Services that don't belong to any sub-clinic ("general services" bucket).
    $generalServices = $clinic->services->whereNull('sub_clinic_id');
@endphp

@if($clinic->subClinics->isEmpty() && $generalServices->isEmpty())
    <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
        @lang('site.no_services_yet')
    </div>
@else
    {{-- One section per sub-clinic, with its services nested inside. --}}
    @foreach($clinic->subClinics as $sub)
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-start justify-between gap-3 mb-1">
                <div class="min-w-0">
                    <h2 class="text-lg font-bold text-gray-800">{{ $sub->display_name }}</h2>
                    @if($sub->category)
                        <p class="inline-flex items-center gap-1 text-xs text-gray-500 mt-0.5">
                            <x-category-icon :emoji="$sub->category->emoji" class="w-3.5 h-3.5" /> {{ $sub->category->display_name }}
                        </p>
                    @endif
                </div>
                <span class="bg-sage-50 text-sage-700 text-xs px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0">
                    {{ __('site.services_count', ['count' => $sub->services->count()]) }}
                </span>
            </div>

            @if($sub->description)
                <p class="text-sm text-gray-600 leading-relaxed mb-4">{{ $sub->description }}</p>
            @endif

            @if($sub->services->isEmpty())
                <p class="text-sm text-gray-400 py-4 text-center">@lang('site.no_services_in_sub_clinic')</p>
            @else
                <div class="divide-y divide-gray-100 border-t border-gray-100 mt-2">
                    @foreach($sub->services as $service)
                        @include('public.partials.service-row', ['service' => $service, 'clinic' => $clinic])
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    {{-- General services (no sub_clinic assigned). --}}
    @if($generalServices->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-start justify-between gap-3 mb-3">
                <h2 class="text-lg font-bold text-gray-800">@lang('site.general_services')</h2>
                <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0">
                    {{ __('site.services_count', ['count' => $generalServices->count()]) }}
                </span>
            </div>
            <div class="divide-y divide-gray-100 border-t border-gray-100">
                @foreach($generalServices as $service)
                    @include('public.partials.service-row', ['service' => $service, 'clinic' => $clinic])
                @endforeach
            </div>
        </div>
    @endif
@endif
