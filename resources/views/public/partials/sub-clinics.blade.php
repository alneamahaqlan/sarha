{{--
    Services tab body — nested view: complex → clinic (sub-clinic) → services.
    Expects: $clinic with relations: subClinics.category, subClinics.services, services.
    Optional: $focusedServiceId — when set, ONLY that service is rendered and
              a "view all" reset pill is shown above the list. Driven by the
              ?service= query string from the homepage offers cards.
--}}
@php
    $focusedServiceId = $focusedServiceId ?? null;
    // Services that don't belong to any sub-clinic ("general services" bucket).
    $generalServices = $clinic->services->whereNull('sub_clinic_id');

    // When focused, narrow every collection to the matching service so the
    // template below stays unchanged and we don't render empty wrappers.
    if ($focusedServiceId) {
        $generalServices = $generalServices->where('id', $focusedServiceId)->values();
        $focusedService  = $clinic->services->firstWhere('id', $focusedServiceId);
    }
@endphp

@if($focusedServiceId)
    <div class="bg-sage-50 border border-sage-200 rounded-xl p-4 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-sage-800">@lang('site.focused_service_notice')</p>
            @if(! empty($focusedService))
                <p class="text-xs text-sage-600 mt-0.5 truncate">{{ $focusedService->name }}</p>
            @endif
        </div>
        <a href="{{ route('clinic.show', $clinic->slug) }}#services"
           class="shrink-0 bg-white text-sage-700 hover:bg-sage-100 ring-1 ring-sage-200 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors whitespace-nowrap">
            @lang('site.view_all_services')
        </a>
    </div>
@endif

@if($clinic->subClinics->isEmpty() && $generalServices->isEmpty())
    <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
        @if($focusedServiceId)
            @lang('site.focused_service_not_found')
        @else
            @lang('site.no_services_yet')
        @endif
    </div>
@else
    {{-- One section per sub-clinic, with its services nested inside. Filtered
         to the focused service when ?service= is set. --}}
    @foreach($clinic->subClinics as $sub)
        @php
            $subServices = $focusedServiceId
                ? $sub->services->where('id', $focusedServiceId)->values()
                : $sub->services;
        @endphp
        @if($focusedServiceId && $subServices->isEmpty())
            @continue
        @endif
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
                    {{ __('site.services_count', ['count' => $subServices->count()]) }}
                </span>
            </div>

            @if($sub->description && ! $focusedServiceId)
                <p class="text-sm text-gray-600 leading-relaxed mb-4">{{ $sub->description }}</p>
            @endif

            @if($subServices->isEmpty())
                <p class="text-sm text-gray-400 py-4 text-center">@lang('site.no_services_in_sub_clinic')</p>
            @else
                <div class="divide-y divide-gray-100 border-t border-gray-100 mt-2">
                    @foreach($subServices as $service)
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
