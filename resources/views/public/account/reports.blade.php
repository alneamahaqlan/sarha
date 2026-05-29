@extends('layouts.public')

@section('title', __('site.account_my_reports'))

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="bg-gradient-to-l from-plum-deep to-plum-primary rounded-xl p-6 text-white mb-6">
        <h1 class="text-2xl font-bold mb-2">@lang('site.account_my_reports')</h1>
        <p class="text-plum-whisper">@lang('site.report_intro')</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1">@include('public.account._nav')</div>

        <div class="lg:col-span-3 space-y-6">
            {{-- Submit a report --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-1">@lang('site.report_new_title')</h2>
                <p class="text-sm text-gray-500 mb-4">@lang('site.report_new_subtitle')</p>

                @if($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 text-sm mb-4">
                        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('account.reports.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.report_type') <span class="text-red-500">*</span></label>
                            <select name="type" required class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-plum-medium">
                                @foreach(\App\Models\CustomerReport::TYPES as $t)
                                    <option value="{{ $t }}" @selected(old('type') === $t)>@lang('site.report_type_' . $t)</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.report_priority')</label>
                            <select name="priority" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-plum-medium">
                                @foreach(['low','medium','high'] as $p)
                                    <option value="{{ $p }}" @selected(old('priority', 'medium') === $p)>@lang('site.report_priority_' . $p)</option>
                                @endforeach
                            </select>
                        </div>
                        @if($clinics->isNotEmpty())
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.report_about_clinic')</label>
                                <select name="clinic_id" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-plum-medium">
                                    <option value="">@lang('site.report_no_clinic')</option>
                                    @foreach($clinics as $c)
                                        <option value="{{ $c->id }}" @selected((int) old('clinic_id') === $c->id)>{{ $c->name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-1">@lang('site.report_about_clinic_hint')</p>
                            </div>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.report_subject') <span class="text-red-500">*</span></label>
                        <input type="text" name="subject" value="{{ old('subject') }}" required maxlength="255"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-plum-medium">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.report_details') <span class="text-red-500">*</span></label>
                        <textarea name="description" rows="4" required minlength="10" maxlength="2000"
                                  placeholder="{{ __('site.report_details_placeholder') }}"
                                  class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-plum-medium">{{ old('description') }}</textarea>
                    </div>
                    <button type="submit" class="bg-plum-primary hover:bg-plum-deep text-white px-6 py-2.5 rounded-lg font-semibold transition-colors">
                        @lang('site.report_submit')
                    </button>
                </form>
            </div>

            {{-- My reports --}}
            <div class="space-y-3">
                <h2 class="text-lg font-bold text-gray-800">@lang('site.report_mine_title')</h2>
                @forelse($reports as $report)
                    <div class="bg-white rounded-xl shadow-sm p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-bold text-gray-800">{{ $report->subject }}</h3>
                                <p class="text-sm text-gray-500 mt-1">{{ Str::limit($report->description, 160) }}</p>
                            </div>
                            @php $sv = ['new'=>'bg-blue-50 text-blue-700','in_review'=>'bg-amber-50 text-amber-700','resolved'=>'bg-emerald-50 text-emerald-700','rejected'=>'bg-red-50 text-red-700'][$report->status] ?? 'bg-gray-100 text-gray-600'; @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full whitespace-nowrap {{ $sv }}">@lang('site.report_status_' . $report->status)</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 mt-3 text-xs text-gray-500">
                            <span class="font-mono bg-gray-100 px-2 py-0.5 rounded" dir="ltr">{{ $report->reference_code }}</span>
                            <span class="bg-plum-whisper text-plum-deep px-2 py-0.5 rounded">@lang('site.report_type_' . $report->type)</span>
                            @if($report->clinic)<span>· {{ $report->clinic->name }}</span>@endif
                            <span class="ms-auto">{{ $report->created_at->diffForHumans() }}</span>
                        </div>
                        @if($report->resolution)
                            <div class="mt-3 bg-emerald-50 border border-emerald-100 rounded-lg p-3 text-sm text-emerald-900">
                                <span class="font-semibold">@lang('site.report_resolution'):</span> {{ $report->resolution }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-400">@lang('site.report_none')</div>
                @endforelse

                @if($reports->hasPages())
                    <div class="mt-4">{{ $reports->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
