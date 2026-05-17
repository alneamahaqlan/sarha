{{--
    Sub-clinics (departments inside a complex)
    Expects: $clinic with subClinics relation loaded (eager: subClinics.services)
--}}
@if($clinic->subClinics->isNotEmpty())
<div class="bg-white rounded-xl shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.sub_clinics_title')</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach($clinic->subClinics as $sub)
            <div class="border border-gray-100 rounded-lg p-4 hover:border-teal-200 transition-colors">
                <div class="flex items-start justify-between mb-1">
                    <h3 class="font-bold text-gray-800">{{ $sub->display_name }}</h3>
                    @if($sub->services->isNotEmpty())
                        <span class="bg-teal-50 text-teal-700 text-xs px-2 py-0.5 rounded-full">
                            {{ __('site.services_count', ['count' => $sub->services->count()]) }}
                        </span>
                    @endif
                </div>
                @if($sub->category)
                    <p class="text-xs text-gray-500 mb-2">{{ $sub->category->emoji }} {{ $sub->category->display_name }}</p>
                @endif
                @if($sub->description)
                    <p class="text-sm text-gray-600 leading-relaxed">{{ Str::limit($sub->description, 120) }}</p>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif
