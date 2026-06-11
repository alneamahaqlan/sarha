@extends('layouts.public')

@section('title', __('site.booking_page_title', ['clinic' => $clinic->name]))

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-sage-600">@lang('site.breadcrumb_home')</a>
        <span class="mx-2">/</span>
        <a href="{{ route('clinic.show', $clinic->slug) }}" class="hover:text-sage-600">{{ $clinic->name }}</a>
        <span class="mx-2">/</span>
        <span class="text-gray-800">@lang('site.book_appointment')</span>
    </nav>

    {{-- Pre-filled service banner — appears when the customer arrived from
         an offer card on the homepage. Confirms exactly which service the
         booking is for; the select below stays editable so they can change. --}}
    @if(! empty($service))
        <div class="bg-sage-50 border border-sage-200 rounded-xl p-4 mb-4 flex items-start gap-3">
            <span class="shrink-0 mt-0.5 inline-flex items-center justify-center w-8 h-8 rounded-full bg-sage-600 text-white">
                <x-icon name="check-circle" class="w-5 h-5" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-sage-800">@lang('site.booking_selected_service_title')</p>
                <p class="text-sm text-sage-900 mt-0.5">
                    {{ $service->name }}
                    @if($service->price)
                        — <span class="font-semibold">{{ number_format($service->price) }} <span class="text-xs font-normal"><x-riyal /></span></span>
                    @endif
                </p>
            </div>
        </div>
    @endif

    {{-- Notice --}}
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-6 text-sm">
        @lang('site.booking_page_notice')
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Form --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">
                    {{ __('site.booking_page_title', ['clinic' => $clinic->name]) }}
                </h1>

                <div class="mb-5"><x-form.errors /></div>

                @if(session('otp_required'))
                    {{-- One-time verification step (first booking → registers the customer). --}}
                    <div class="bg-sage-50 border border-sage-200 text-sage-900 rounded-lg p-4 text-sm mb-5 space-y-1">
                        <p class="font-semibold">@lang('site.otp_step_title')</p>
                        <p>@lang('site.otp_step_intro')</p>
                        <p class="text-xs text-sage-700">{{ __('site.otp_sent_to', ['phone' => session('otp_phone')]) }}</p>
                        @if(session('dev_code'))
                            <p class="text-xs font-mono bg-white inline-block px-2 py-1 rounded border border-sage-200">DEV: {{ session('dev_code') }}</p>
                        @endif
                    </div>

                    <form novalidate method="POST" action="{{ route('clinic.book.verify', $clinic->slug) }}" class="space-y-5">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.otp_code_label') <span class="text-red-500">*</span></label>
                            <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6"
                                   autocomplete="one-time-code" required autofocus
                                   class="w-full border border-gray-200 rounded-lg px-4 py-3 text-center tracking-[0.5em] text-lg focus:outline-none focus:ring-2 focus:ring-sage-400" dir="ltr">
                        </div>
                        <p class="text-xs text-gray-500">@lang('site.otp_step_terms')</p>
                        <button type="submit" class="w-full bg-sage-600 text-white py-3.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors text-lg">
                            @lang('site.otp_verify_submit')
                        </button>
                    </form>
                @else
                <form novalidate method="POST" action="{{ route('clinic.book', $clinic->slug) }}" class="space-y-5" id="bookingForm">
                    @csrf

                    @auth('web')
                        {{-- Patient picker — only for logged-in bookers. Toggles
                             between "myself" (default) and "one of my saved
                             relatives" so the booker doesn't have to retype
                             a parent/child's name+phone every time. --}}
                        @php
                            $oldBookingFor = old('booking_for', 'self');
                            $oldRelativeId = (int) old('relative_id', 0);
                            $canAddRelative = $relatives->count() < \App\Models\Relative::MAX_PER_USER;
                        @endphp
                        <div class="border border-gray-200 rounded-xl p-4 bg-gray-50/50" data-relative-picker>
                            <p class="text-sm font-semibold text-gray-800 mb-3">@lang('site.booking_for_label')</p>
                            {{-- Server sees this single field. JS rewrites it to
                                 self / relative_existing / relative_new based on
                                 the radio choice + selected card / new fields. --}}
                            <input type="hidden" name="booking_for" value="self" data-booking-for>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4">
                                <label class="flex items-center gap-2 cursor-pointer border border-gray-200 rounded-lg px-3 py-2.5 bg-white hover:border-sage-300 transition-colors">
                                    <input type="radio" name="booking_for_choice" value="self" data-mode="self"
                                           class="text-sage-600 focus:ring-sage-400"
                                           @checked($oldBookingFor === 'self')>
                                    <span class="text-sm font-medium text-gray-800">@lang('site.booking_for_self')</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer border border-gray-200 rounded-lg px-3 py-2.5 bg-white hover:border-sage-300 transition-colors">
                                    <input type="radio" name="booking_for_choice" value="relative" data-mode="relative"
                                           class="text-sage-600 focus:ring-sage-400"
                                           @checked(in_array($oldBookingFor, ['relative', 'relative_existing', 'relative_new']))>
                                    <span class="text-sm font-medium text-gray-800">@lang('site.booking_for_relative')</span>
                                </label>
                            </div>

                            {{-- Relative chooser — visible only when "relative" mode is selected. --}}
                            <div data-relative-panel class="hidden space-y-3">
                                @if($relatives->isEmpty())
                                    {{-- 0 saved relatives → no cards, fields are visible by default. --}}
                                @else
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" data-relative-cards>
                                        @foreach($relatives as $rel)
                                            <div class="relative border border-gray-200 rounded-lg bg-white hover:border-sage-300 transition-colors has-[:checked]:border-sage-500 has-[:checked]:bg-sage-50"
                                                 data-relative-card data-relative-id="{{ $rel->id }}">
                                                <label class="flex items-start gap-3 cursor-pointer p-3 pe-14">
                                                    <input type="radio" name="relative_id" value="{{ $rel->id }}"
                                                           class="mt-1 text-sage-600 focus:ring-sage-400"
                                                           data-relative-name="{{ $rel->name }}"
                                                           data-relative-phone="{{ $rel->phone }}"
                                                           @checked($oldRelativeId === $rel->id)>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm font-semibold text-gray-800 truncate" data-relative-display-name>{{ $rel->name }}</p>
                                                        <p class="text-xs text-sage-700 mt-0.5" data-relative-display-relationship>
                                                            {{ $rel->relationship_type === 'other' ? ($rel->relationship_label ?: __('site.relative_type_other')) : __('site.relative_type.' . $rel->relationship_type) }}
                                                        </p>
                                                        <p class="text-xs text-gray-500 mt-0.5" dir="ltr" data-relative-display-phone>{{ $rel->phone }}</p>
                                                    </div>
                                                </label>
                                                {{-- Small edit / delete buttons in the corner. type=button
                                                     so they don't submit the booking form. --}}
                                                <div class="absolute top-2 end-2 flex items-center gap-1">
                                                    <button type="button" data-edit-relative
                                                            class="w-7 h-7 rounded-md text-gray-400 hover:text-sage-700 hover:bg-sage-50 flex items-center justify-center transition-colors"
                                                            title="{{ __('site.relative_edit') }}" aria-label="{{ __('site.relative_edit') }}"
                                                            data-name="{{ $rel->name }}"
                                                            data-relationship-type="{{ $rel->relationship_type }}"
                                                            data-relationship-label="{{ $rel->relationship_label }}"
                                                            data-phone="{{ $rel->phone }}">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                    </button>
                                                    <button type="button" data-delete-relative
                                                            class="w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center transition-colors"
                                                            title="{{ __('site.relative_delete') }}" aria-label="{{ __('site.relative_delete') }}">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" /></svg>
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Inline edit pane — single shared slot. Opens above the
                                         "+ add new" button when the user clicks edit on a card.
                                         Submits via fetch to PATCH /account/relatives/{id}. --}}
                                    <div data-edit-relative-pane class="hidden space-y-3 border border-sage-200 bg-sage-50/40 rounded-lg p-3 mt-2">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-semibold text-sage-800">@lang('site.relative_edit')</p>
                                            <button type="button" data-cancel-edit-relative class="text-xs text-gray-500 hover:text-gray-800">@lang('site.cancel')</button>
                                        </div>
                                        <input type="hidden" data-edit-relative-id>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">@lang('site.relative_name') <span class="text-red-500">*</span></label>
                                                <input type="text" data-edit-field="name" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">@lang('site.relative_relationship') <span class="text-red-500">*</span></label>
                                                <select data-edit-field="relationship_type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                                                    <option value="">—</option>
                                                    @foreach($relativeTypes as $type)
                                                        <option value="{{ $type }}">{{ __('site.relative_type.' . $type) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div data-edit-label-field class="hidden">
                                                <label class="block text-xs font-medium text-gray-700 mb-1">@lang('site.relative_relationship_label')</label>
                                                <input type="text" data-edit-field="relationship_label" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">@lang('site.relative_phone') <span class="text-red-500">*</span></label>
                                                <input type="tel" data-edit-field="phone" pattern="05[0-9]{8}" placeholder="05XXXXXXXX" dir="ltr"
                                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                                            </div>
                                        </div>
                                        <button type="button" data-save-edit-relative
                                                class="bg-sage-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-sage-700 transition-colors">
                                            @lang('site.save_changes')
                                        </button>
                                        <p data-edit-error class="text-xs text-red-600 hidden"></p>
                                    </div>
                                @endif

                                @if($canAddRelative)
                                    <button type="button" data-add-relative-btn
                                            class="text-sm text-sage-700 hover:text-sage-900 font-medium inline-flex items-center gap-1.5">
                                        <span class="text-base leading-none">+</span> @lang('site.add_new_relative')
                                    </button>

                                    {{-- New-relative inline form — hidden until user clicks "+ add" (or
                                         shown by default when they have 0 saved relatives). --}}
                                    <div data-new-relative-fields class="@if($relatives->isNotEmpty()) hidden @endif space-y-3 border-t border-gray-200 pt-3 mt-2">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">@lang('site.relative_name') <span class="text-red-500">*</span></label>
                                                <input type="text" name="relative[name]" value="{{ old('relative.name') }}"
                                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">@lang('site.relative_relationship') <span class="text-red-500">*</span></label>
                                                <select name="relative[relationship_type]" data-relationship-select
                                                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                                                    <option value="">—</option>
                                                    @foreach($relativeTypes as $type)
                                                        <option value="{{ $type }}" @selected(old('relative.relationship_type') === $type)>
                                                            {{ __('site.relative_type.' . $type) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div data-relationship-label-field class="@if(old('relative.relationship_type') !== 'other') hidden @endif">
                                                <label class="block text-xs font-medium text-gray-700 mb-1">@lang('site.relative_relationship_label')</label>
                                                <input type="text" name="relative[relationship_label]" value="{{ old('relative.relationship_label') }}"
                                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">@lang('site.relative_phone') <span class="text-red-500">*</span></label>
                                                <input type="tel" name="relative[phone]" value="{{ old('relative.phone') }}"
                                                       inputmode="tel" pattern="05[0-9]{8}" placeholder="05XXXXXXXX" dir="ltr"
                                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                        @lang('site.relative_max_reached')
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endauth

                    {{-- Patient identity fields — for self mode (visible) or guest flow.
                         JS hides them when "relative" mode is selected since the
                         server derives customer_name/phone from the relative row. --}}
                    <div data-self-fields class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.full_name') <span class="text-red-500">*</span></label>
                            <input type="text" name="customer_name" value="{{ old('customer_name', auth('web')->user()?->name ?? ($identity['name'] ?? '')) }}"
                                   autocomplete="name"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.phone_number') <span class="text-red-500">*</span></label>
                            <input type="tel" name="customer_phone" value="{{ old('customer_phone', auth('web')->user()?->phone ?? ($identity['phone'] ?? '')) }}"
                                   inputmode="tel" autocomplete="tel" pattern="05[0-9]{8}" placeholder="05XXXXXXXX"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400" dir="ltr">
                        </div>
                    </div>

                    @if($clinic->services->isNotEmpty())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.requested_service')</label>
                            <x-form.select name="service_id">
                                <option value="">@lang('site.not_specified')</option>
                                @foreach($clinic->services as $svc)
                                    <option value="{{ $svc->id }}" @selected(old('service_id', $service?->id) == $svc->id)>{{ $svc->name }}</option>
                                @endforeach
                            </x-form.select>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.notes_optional')</label>
                        <textarea name="notes" rows="4" maxlength="1000"
                                  class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">{{ old('notes') }}</textarea>
                    </div>

                    @guest('web')
                        <p class="text-xs text-gray-500">@lang('site.otp_step_terms')</p>
                    @endguest

                    <button type="submit" class="w-full bg-sage-600 text-white py-3.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors text-lg">
                        @lang('site.submit_booking')
                    </button>
                </form>

                @auth('web')
                {{-- Patient picker behaviour: toggle the "self" vs "relative"
                     panels, swap required attributes, and toggle the
                     relationship-label field for the "other" type. The
                     server-side validation is authoritative — this layer
                     only steers UX. --}}
                <script>
                (function () {
                    const form = document.getElementById('bookingForm');
                    if (!form) return;

                    const picker = form.querySelector('[data-relative-picker]');
                    if (!picker) return;

                    const selfFields = form.querySelector('[data-self-fields]');
                    const relPanel = picker.querySelector('[data-relative-panel]');
                    const newRelFields = picker.querySelector('[data-new-relative-fields]');
                    const addBtn = picker.querySelector('[data-add-relative-btn]');
                    const cards = picker.querySelectorAll('input[name="relative_id"]');
                    const newName = picker.querySelector('input[name="relative[name]"]');
                    const newType = picker.querySelector('select[name="relative[relationship_type]"]');
                    const newPhone = picker.querySelector('input[name="relative[phone]"]');
                    const newLabelField = picker.querySelector('[data-relationship-label-field]');
                    const customerName = selfFields?.querySelector('input[name="customer_name"]');
                    const customerPhone = selfFields?.querySelector('input[name="customer_phone"]');
                    const hiddenBookingFor = picker.querySelector('[data-booking-for]');

                    const setRequired = (el, required) => {
                        if (!el) return;
                        if (required) el.setAttribute('required', 'required');
                        else el.removeAttribute('required');
                    };

                    function applyMode() {
                        const mode = form.querySelector('input[name="booking_for_choice"]:checked')?.value || 'self';
                        if (mode === 'self') {
                            selfFields?.classList.remove('hidden');
                            relPanel?.classList.add('hidden');
                            setRequired(customerName, true);
                            setRequired(customerPhone, true);
                            setRequired(newName, false);
                            setRequired(newType, false);
                            setRequired(newPhone, false);
                            if (hiddenBookingFor) hiddenBookingFor.value = 'self';
                        } else {
                            selfFields?.classList.add('hidden');
                            relPanel?.classList.remove('hidden');
                            setRequired(customerName, false);
                            setRequired(customerPhone, false);
                            applyRelativeSubmode();
                        }
                    }

                    function applyRelativeSubmode() {
                        const selected = picker.querySelector('input[name="relative_id"]:checked');
                        const newFieldsVisible = newRelFields && !newRelFields.classList.contains('hidden');

                        if (newFieldsVisible) {
                            if (hiddenBookingFor) hiddenBookingFor.value = 'relative_new';
                            setRequired(newName, true);
                            setRequired(newType, true);
                            setRequired(newPhone, true);
                            cards.forEach((c) => { c.checked = false; });
                        } else {
                            if (hiddenBookingFor) hiddenBookingFor.value = 'relative_existing';
                            setRequired(newName, false);
                            setRequired(newType, false);
                            setRequired(newPhone, false);
                        }
                    }

                    form.querySelectorAll('input[name="booking_for_choice"]').forEach((r) => {
                        r.addEventListener('change', applyMode);
                    });
                    cards.forEach((c) => {
                        c.addEventListener('change', () => {
                            newRelFields?.classList.add('hidden');
                            applyRelativeSubmode();
                        });
                    });
                    addBtn?.addEventListener('click', () => {
                        newRelFields?.classList.remove('hidden');
                        cards.forEach((c) => { c.checked = false; });
                        applyRelativeSubmode();
                        newName?.focus();
                    });
                    newType?.addEventListener('change', () => {
                        if (newType.value === 'other') newLabelField?.classList.remove('hidden');
                        else newLabelField?.classList.add('hidden');
                    });

                    // ===== Edit + delete (AJAX) =====
                    // The booker can manage their saved list without leaving
                    // the form. Routes return JSON; the UI updates the cards
                    // in place so any in-progress booking input is preserved.
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        || form.querySelector('input[name="_token"]')?.value;
                    const editPane = picker.querySelector('[data-edit-relative-pane]');
                    const editIdInput = picker.querySelector('[data-edit-relative-id]');
                    const editFields = {
                        name: picker.querySelector('[data-edit-field="name"]'),
                        relationship_type: picker.querySelector('[data-edit-field="relationship_type"]'),
                        relationship_label: picker.querySelector('[data-edit-field="relationship_label"]'),
                        phone: picker.querySelector('[data-edit-field="phone"]'),
                    };
                    const editLabelField = picker.querySelector('[data-edit-label-field]');
                    const editSaveBtn = picker.querySelector('[data-save-edit-relative]');
                    const editCancelBtn = picker.querySelector('[data-cancel-edit-relative]');
                    const editError = picker.querySelector('[data-edit-error]');

                    function openEditPane(card, btn) {
                        if (!editPane) return;
                        const id = card.getAttribute('data-relative-id');
                        editIdInput.value = id;
                        editFields.name.value = btn.dataset.name || '';
                        editFields.relationship_type.value = btn.dataset.relationshipType || '';
                        editFields.relationship_label.value = btn.dataset.relationshipLabel || '';
                        editFields.phone.value = btn.dataset.phone || '';
                        if (editFields.relationship_type.value === 'other') {
                            editLabelField?.classList.remove('hidden');
                        } else {
                            editLabelField?.classList.add('hidden');
                        }
                        editError?.classList.add('hidden');
                        editPane.classList.remove('hidden');
                        newRelFields?.classList.add('hidden');
                        editFields.name.focus();
                    }

                    editFields.relationship_type?.addEventListener('change', () => {
                        if (editFields.relationship_type.value === 'other') editLabelField?.classList.remove('hidden');
                        else editLabelField?.classList.add('hidden');
                    });

                    editCancelBtn?.addEventListener('click', () => editPane?.classList.add('hidden'));

                    editSaveBtn?.addEventListener('click', async () => {
                        const id = editIdInput.value;
                        if (!id) return;
                        editError?.classList.add('hidden');
                        editSaveBtn.disabled = true;
                        try {
                            const res = await fetch(`/account/relatives/${id}`, {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    name: editFields.name.value,
                                    relationship_type: editFields.relationship_type.value,
                                    relationship_label: editFields.relationship_label.value || null,
                                    phone: editFields.phone.value,
                                }),
                            });
                            if (!res.ok) {
                                const body = await res.json().catch(() => ({}));
                                const firstMsg = body?.errors ? Object.values(body.errors)[0]?.[0] : (body?.message || 'Error');
                                editError.textContent = firstMsg;
                                editError.classList.remove('hidden');
                                return;
                            }
                            const { data } = await res.json();
                            // Swap the card text in place.
                            const card = picker.querySelector(`[data-relative-card][data-relative-id="${id}"]`);
                            if (card) {
                                card.querySelector('[data-relative-display-name]').textContent = data.name;
                                card.querySelector('[data-relative-display-relationship]').textContent = data.relationship_display;
                                card.querySelector('[data-relative-display-phone]').textContent = data.phone;
                                const radio = card.querySelector('input[name="relative_id"]');
                                if (radio) {
                                    radio.dataset.relativeName = data.name;
                                    radio.dataset.relativePhone = data.phone;
                                }
                                const editBtn = card.querySelector('[data-edit-relative]');
                                if (editBtn) {
                                    editBtn.dataset.name = data.name;
                                    editBtn.dataset.relationshipType = data.relationship_type;
                                    editBtn.dataset.relationshipLabel = data.relationship_label || '';
                                    editBtn.dataset.phone = data.phone;
                                }
                            }
                            editPane.classList.add('hidden');
                        } catch (e) {
                            editError.textContent = 'Network error';
                            editError.classList.remove('hidden');
                        } finally {
                            editSaveBtn.disabled = false;
                        }
                    });

                    picker.querySelectorAll('[data-edit-relative]').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            const card = btn.closest('[data-relative-card]');
                            if (card) openEditPane(card, btn);
                        });
                    });

                    picker.querySelectorAll('[data-delete-relative]').forEach((btn) => {
                        btn.addEventListener('click', async () => {
                            const card = btn.closest('[data-relative-card]');
                            if (!card) return;
                            const id = card.getAttribute('data-relative-id');
                            const confirmMsg = '{{ __('site.relative_delete_confirm') }}';
                            if (!confirm(confirmMsg)) return;
                            btn.disabled = true;
                            try {
                                const res = await fetch(`/account/relatives/${id}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': csrf,
                                        'Accept': 'application/json',
                                    },
                                });
                                if (!res.ok) {
                                    btn.disabled = false;
                                    return;
                                }
                                // Remove the card and refresh submode (might
                                // need to switch to relative_new if this was
                                // the last card and the new-fields panel is hidden).
                                card.remove();
                                editPane?.classList.add('hidden');
                                const remaining = picker.querySelectorAll('[data-relative-card]').length;
                                if (remaining === 0) {
                                    // Show the new-fields form so the user
                                    // can still complete a booking; mirrors
                                    // the "0 saved relatives" first-time UX.
                                    newRelFields?.classList.remove('hidden');
                                }
                                applyRelativeSubmode();
                            } catch (e) {
                                btn.disabled = false;
                            }
                        });
                    });

                    applyMode();
                })();
                </script>
                @endauth
                @endif
            </div>
        </div>

        {{-- Sidebar: Summary --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm p-6 lg:sticky lg:top-20">
                <h3 class="font-bold text-gray-800 mb-4">@lang('site.booking_summary')</h3>

                <div class="space-y-3 text-sm">
                    <div>
                        <p class="text-gray-500 mb-0.5">@lang('site.booking_target_clinic')</p>
                        <p class="font-semibold text-gray-800">{{ $clinic->name }}</p>
                    </div>

                    @if($clinic->city)
                        <div>
                            <p class="text-gray-500 mb-0.5">@lang('site.booking_target_city')</p>
                            <p class="font-medium text-gray-800">{{ $clinic->city->display_name }}</p>
                        </div>
                    @endif

                    @if($service)
                        <div>
                            <p class="text-gray-500 mb-0.5">@lang('site.booking_target_service')</p>
                            <p class="font-medium text-gray-800">{{ $service->name }}</p>
                            @if($service->price)
                                <p class="text-sage-700 font-bold mt-1">
                                    {{ number_format($service->price) }} <span class="text-xs font-normal"><x-riyal /></span>
                                </p>
                            @endif
                        </div>
                    @endif
                </div>

                <hr class="my-4 border-gray-100">

                <p class="text-xs text-gray-500 leading-relaxed">
                    @lang('site.how_not_appointment_notice')
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
