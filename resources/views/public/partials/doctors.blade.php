{{--
    Doctors showcase tab. Expects: $clinic with `doctors` loaded.
--}}
@if($clinic->doctors->isEmpty())
    <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
        @lang('site.no_doctors_yet')
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach($clinic->doctors as $doctor)
            <div class="bg-white rounded-xl shadow-sm p-5 flex gap-4 items-start">
                @if($doctor->photo)
                    <img src="{{ Storage::url($doctor->photo) }}" alt="{{ $doctor->name }}" loading="lazy"
                         class="h-16 w-16 rounded-full object-cover flex-shrink-0">
                @else
                    <span class="h-16 w-16 rounded-full bg-teal-50 text-teal-600 flex items-center justify-center flex-shrink-0">
                        <x-icon name="user" class="w-8 h-8" />
                    </span>
                @endif
                <div class="min-w-0">
                    <h3 class="font-bold text-gray-800">{{ $doctor->name }}</h3>
                    @if($doctor->specialty)
                        <p class="text-sm text-teal-700">{{ $doctor->specialty }}</p>
                    @endif
                    <div class="flex flex-wrap gap-x-3 gap-y-1 mt-1 text-xs text-gray-500">
                        @if($doctor->years_experience)
                            <span>{{ __('site.years_experience', ['count' => $doctor->years_experience]) }}</span>
                        @endif
                        @if($doctor->gender)
                            <span>{{ __('site.doctor_gender_' . $doctor->gender) }}</span>
                        @endif
                        @if($doctor->subClinic)
                            <span class="inline-flex items-center gap-1"><x-icon name="building" class="w-3 h-3" /> {{ $doctor->subClinic->display_name }}</span>
                        @endif
                    </div>
                    @if($doctor->qualifications)
                        <p class="text-xs text-gray-600 mt-1.5">{{ $doctor->qualifications }}</p>
                    @endif
                    @if($doctor->university)
                        <p class="text-xs text-gray-500 mt-1">{{ __('site.doctor_university') }}: {{ $doctor->university }}</p>
                    @endif
                    @if($doctor->languages)
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('site.doctor_languages') }}: {{ $doctor->languages }}</p>
                    @endif
                    @if($doctor->bio)
                        <p class="text-sm text-gray-600 mt-2 leading-relaxed">{{ Str::limit($doctor->bio, 140) }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
