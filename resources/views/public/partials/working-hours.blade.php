{{--
    Working hours block.
    Expects: $clinic (with workingHours relation)
--}}
@php
    use App\Models\ClinicWorkingHour;

    $byDay = $clinic->workingHours->keyBy('day_of_week');
    $todayIdx = (int) now()->dayOfWeek; // 0 (Sun) … 6 (Sat)
    $today = $byDay->get($todayIdx);

    $isOpenNow = false;
    if ($today && $today->is_open && $today->opens_at && $today->closes_at) {
        $now = now();
        $opens = now()->setTimeFromTimeString($today->opens_at);
        $closes = now()->setTimeFromTimeString($today->closes_at);
        if ($closes->lessThanOrEqualTo($opens)) {
            $closes->addDay();
        }
        $isOpenNow = $now->between($opens, $closes);
    }
@endphp

@if($clinic->workingHours->isNotEmpty())
<div class="bg-white rounded-xl shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-gray-800">{{ $sidebarTitleOverride ?? __('site.working_hours_title') }}</h3>
        @if($isOpenNow)
            <span class="bg-green-50 text-green-700 px-3 py-1 rounded-full text-xs font-semibold">
                ● @lang('site.working_hours_open_now')
            </span>
        @else
            <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-semibold">
                ○ @lang('site.working_hours_closed')
            </span>
        @endif
    </div>

    <div class="space-y-1.5 text-sm">
        @for($d = 0; $d < 7; $d++)
            @php
                $row = $byDay->get($d);
                $isToday = $d === $todayIdx;
            @endphp
            <div class="flex items-center justify-between py-1 px-2 rounded {{ $isToday ? 'bg-sage-50' : '' }}">
                <span class="{{ $isToday ? 'font-bold text-sage-700' : 'text-gray-600' }}">
                    {{ __('site.days.' . $d) }}
                </span>
                @if($row && $row->is_open && $row->opens_at && $row->closes_at)
                    <span class="{{ $isToday ? 'font-semibold text-sage-700' : 'text-gray-700' }}" dir="ltr">
                        {{ \Carbon\Carbon::parse($row->opens_at)->format('h:i A') }}
                        —
                        {{ \Carbon\Carbon::parse($row->closes_at)->format('h:i A') }}
                    </span>
                @else
                    <span class="text-gray-400 text-xs">@lang('site.working_hours_closed_today')</span>
                @endif
            </div>
        @endfor
    </div>
</div>
@endif
