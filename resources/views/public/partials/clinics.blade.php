{{--
    Clinics tab body — directory of the complex's sub-clinics (الفروع /
    الأقسام). Shows each sub-clinic as a card with its specialty,
    description, and headline counts (services + doctors), with a link
    that scrolls to the services tab filtered to that sub-clinic.
    Expects: $clinic with subClinics.{category,services,doctors} loaded.

    This is the structural directory of the complex. The "Services" tab
    is the price-list view of the same data, grouped the same way; this
    tab is the navigational view — pick a department, jump to its
    services.
--}}
@if($clinic->subClinics->isEmpty())
    <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
        @lang('site.no_clinics_yet')
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach($clinic->subClinics as $sub)
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-100 hover:shadow-md transition-shadow p-5 flex flex-col">
                <div class="flex items-start gap-3 mb-2">
                    @if($sub->category)
                        <div class="shrink-0 w-11 h-11 rounded-xl bg-sage-mist text-sage-primary flex items-center justify-center">
                            <x-category-icon :emoji="$sub->category->emoji" class="w-6 h-6" />
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-gray-800 leading-snug">{{ $sub->display_name }}</h3>
                        @if($sub->category)
                            <p class="text-xs text-gray-500 mt-0.5">{{ $sub->category->display_name }}</p>
                        @endif
                    </div>
                </div>

                @if($sub->description)
                    <p class="text-sm text-gray-600 leading-relaxed mb-3 line-clamp-3">{{ $sub->description }}</p>
                @endif

                <div class="mt-auto flex items-center flex-wrap gap-2 pt-2 border-t border-gray-100">
                    <span class="inline-flex items-center gap-1 bg-sage-50 text-sage-700 text-xs px-2 py-1 rounded-full">
                        {{ __('site.services_count', ['count' => $sub->services->count()]) }}
                    </span>
                    @if(isset($sub->doctors))
                        <span class="inline-flex items-center gap-1 bg-gold-whisper text-gold-deep text-xs px-2 py-1 rounded-full">
                            {{ __('site.doctors_count', ['count' => $sub->doctors->count()]) }}
                        </span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
