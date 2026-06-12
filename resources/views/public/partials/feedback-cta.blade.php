{{--
    Feedback call-to-action — two buttons that let a customer either file a
    COMPLAINT about a clinic (routed straight to that clinic's inbox, with
    admins able to see it too) or REPORT a platform/technical issue (routed
    to admins only). Rendered at the bottom of the homepage and each clinic
    page.

    Optional: $clinic — when present (clinic page), the complaint is pinned
    to that clinic; otherwise (homepage) the customer picks from the clinics
    they've dealt with, or files a general complaint.

    Submissions reuse the existing account.complaints.store /
    account.reports.store endpoints, which redirect back() with a flash.
--}}
@php
    $clinic = $clinic ?? null;
    $feedbackClinics = collect();
    if (! $clinic && auth('web')->check()) {
        $feedbackClinics = \App\Models\Clinic::whereIn('id', auth('web')->user()->bookings()->select('clinic_id'))
            ->orderBy('name')->get(['id', 'name']);
    }
    $fieldCls = 'w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400';
@endphp

<section class="max-w-7xl mx-auto px-4 py-12" x-data="{ complaint: false, report: false }">
    <div class="rounded-2xl bg-gradient-to-l from-sage-50 to-gold-whisper/40 ring-1 ring-sage-100 p-6 md:p-8">
        <div class="text-center mb-6">
            <h2 class="font-display text-2xl font-bold text-charcoal">@lang('site.feedback_title')</h2>
            <p class="text-gray-500 text-sm mt-1">@lang('site.feedback_subtitle')</p>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 max-w-2xl mx-auto">
            <button type="button" @click="complaint = true"
                    class="group flex items-start gap-3 text-start bg-white rounded-xl ring-1 ring-gray-100 hover:ring-sage-300 hover:shadow-md transition-all p-5">
                <span class="shrink-0 w-11 h-11 rounded-full bg-sage-100 text-sage-700 flex items-center justify-center">
                    <x-icon name="warning" class="w-5 h-5" />
                </span>
                <span class="min-w-0">
                    <span class="block font-semibold text-gray-800 group-hover:text-sage-700">@lang('site.feedback_complaint_cta')</span>
                    <span class="block text-xs text-gray-500 mt-0.5">@lang('site.feedback_complaint_hint')</span>
                </span>
            </button>

            <button type="button" @click="report = true"
                    class="group flex items-start gap-3 text-start bg-white rounded-xl ring-1 ring-gray-100 hover:ring-plum-medium hover:shadow-md transition-all p-5">
                <span class="shrink-0 w-11 h-11 rounded-full bg-plum-soft/30 text-plum-primary flex items-center justify-center">
                    <x-icon name="clipboard" class="w-5 h-5" />
                </span>
                <span class="min-w-0">
                    <span class="block font-semibold text-gray-800 group-hover:text-plum-primary">@lang('site.feedback_report_cta')</span>
                    <span class="block text-xs text-gray-500 mt-0.5">@lang('site.feedback_report_hint')</span>
                </span>
            </button>
        </div>
    </div>

    {{-- ===== Complaint modal ===== --}}
    <div x-show="complaint" x-cloak @keydown.escape.window="complaint = false"
         class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-4">
        <div class="absolute inset-0 bg-black/50" @click="complaint = false"></div>
        <div class="relative bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-2xl shadow-xl max-h-[90vh] overflow-y-auto"
             x-show="complaint" x-transition>
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="font-bold text-gray-800">@lang('site.feedback_complaint_title')</h3>
                <button type="button" @click="complaint = false" class="text-gray-400 hover:text-gray-600" aria-label="@lang('common.cancel')">✕</button>
            </div>
            <div class="p-5">
                <div class="bg-sage-50 border border-sage-100 text-sage-800 rounded-lg p-3 text-xs mb-4">
                    @lang('site.feedback_complaint_notice')
                </div>

                @auth('web')
                    <form novalidate method="POST" action="{{ route('account.complaints.store') }}" class="space-y-4">
                        @csrf
                        @if($clinic)
                            <input type="hidden" name="clinic_id" value="{{ $clinic->id }}">
                            <p class="text-sm text-gray-600">@lang('site.feedback_complaint_about'): <span class="font-semibold text-gray-800">{{ $clinic->name }}</span></p>
                        @elseif($feedbackClinics->isNotEmpty())
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.complaint_about_clinic')</label>
                                <x-form.select name="clinic_id">
                                    <option value="">@lang('site.feedback_general_complaint')</option>
                                    @foreach($feedbackClinics as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </x-form.select>
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.complaint_type') <span class="text-red-500">*</span></label>
                            <x-form.select name="type" required>
                                @foreach(\App\Models\Complaint::TYPES as $t)
                                    <option value="{{ $t }}">@lang('site.complaint_type_' . $t)</option>
                                @endforeach
                            </x-form.select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.complaint_subject') <span class="text-red-500">*</span></label>
                            <input type="text" name="subject" required maxlength="255" class="{{ $fieldCls }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.complaint_details') <span class="text-red-500">*</span></label>
                            <textarea name="description" rows="4" required minlength="10" maxlength="2000" class="{{ $fieldCls }}"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-sage-600 hover:bg-sage-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors">
                            @lang('site.complaint_submit')
                        </button>
                    </form>
                @else
                    <div class="text-center py-4">
                        <p class="text-sm text-gray-600 mb-4">@lang('site.feedback_login_prompt')</p>
                        <a href="{{ route('login') }}" class="inline-block bg-sage-600 hover:bg-sage-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors">
                            @lang('site.feedback_login_cta')
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </div>

    {{-- ===== Platform report modal ===== --}}
    <div x-show="report" x-cloak @keydown.escape.window="report = false"
         class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-4">
        <div class="absolute inset-0 bg-black/50" @click="report = false"></div>
        <div class="relative bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-2xl shadow-xl max-h-[90vh] overflow-y-auto"
             x-show="report" x-transition>
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="font-bold text-gray-800">@lang('site.feedback_report_title')</h3>
                <button type="button" @click="report = false" class="text-gray-400 hover:text-gray-600" aria-label="@lang('common.cancel')">✕</button>
            </div>
            <div class="p-5">
                <div class="bg-plum-soft/20 border border-plum-soft/40 text-plum-deep rounded-lg p-3 text-xs mb-4">
                    @lang('site.feedback_report_notice')
                </div>

                @auth('web')
                    <form novalidate method="POST" action="{{ route('account.reports.store') }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.report_type') <span class="text-red-500">*</span></label>
                                <x-form.select name="type" required>
                                    @foreach(\App\Models\CustomerReport::TYPES as $t)
                                        <option value="{{ $t }}" @selected($t === 'bug')>@lang('site.report_type_' . $t)</option>
                                    @endforeach
                                </x-form.select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.report_priority')</label>
                                <x-form.select name="priority">
                                    @foreach(['low','medium','high'] as $p)
                                        <option value="{{ $p }}" @selected($p === 'medium')>@lang('site.report_priority_' . $p)</option>
                                    @endforeach
                                </x-form.select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.report_subject') <span class="text-red-500">*</span></label>
                            <input type="text" name="subject" required maxlength="255" class="{{ $fieldCls }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.report_details') <span class="text-red-500">*</span></label>
                            <textarea name="description" rows="4" required minlength="10" maxlength="2000"
                                      placeholder="{{ __('site.report_details_placeholder') }}" class="{{ $fieldCls }}"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-plum-primary hover:bg-plum-deep text-white px-6 py-2.5 rounded-lg font-semibold transition-colors">
                            @lang('site.report_submit')
                        </button>
                    </form>
                @else
                    <div class="text-center py-4">
                        <p class="text-sm text-gray-600 mb-4">@lang('site.feedback_login_prompt')</p>
                        <a href="{{ route('login') }}" class="inline-block bg-plum-primary hover:bg-plum-deep text-white px-6 py-2.5 rounded-lg font-semibold transition-colors">
                            @lang('site.feedback_login_cta')
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</section>
