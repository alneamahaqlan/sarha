{{--
    Red validation summary box for public Blade forms.
    Lists Laravel validation errors after a failed submit. Shows nothing when valid.

    Usage:
      <x-form.errors />                                 (all errors)
      <x-form.errors :only="['phone','city_id']" />     (only these fields)
--}}
@props(['only' => null])

@php
    $messages = $only
        ? collect($only)->flatMap(fn ($f) => $errors->get($f))->all()
        : $errors->all();
@endphp

@if (!empty($messages))
    <div role="alert" class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
        <p class="font-semibold mb-1">@lang('site.form_errors_title')</p>
        <ul class="list-disc ps-5 space-y-0.5">
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
