{{--
    Searchable dropdown for public Blade forms.

    Keeps a real <select> (positioned invisibly over the control) as the actual
    form element, so native submit, `required`, `name`, `old()` and existing
    controllers keep working unchanged. A visible Alpine layer adds search.

    Usage (pass <option>s as the slot, exactly like a normal <select>):
      <x-form.select name="city_id" required>
          <option value="">@lang('site.search_all_cities')</option>
          @foreach($cities as $c)
              <option value="{{ $c->id }}" @selected(old('city_id') == $c->id)>{{ $c->display_name }}</option>
          @endforeach
      </x-form.select>

    Props:
      name         (required) form field name
      id           (optional) defaults to name
      placeholder  (optional) text when nothing selected
      searchable   (optional) true|false; defaults to auto (shown when > 5 options)
--}}
@props(['name', 'id' => null, 'placeholder' => null, 'searchable' => null])

@php $id = $id ?? $name; @endphp

<div
    x-data="{
        open: false,
        query: '',
        label: '',
        value: '',
        options: [],
        searchable: @js($searchable),
        norm(s) {
            return (s || '').toString().toLowerCase()
                .replace(/[ً-ْٰ]/g, '')
                .replace(/[إأآا]/g, 'ا')
                .replace(/ى/g, 'ي')
                .replace(/ة/g, 'ه')
                .replace(/\s+/g, ' ').trim();
        },
        init() {
            const sel = this.$refs.native;
            this.options = Array.from(sel.options).map(o => ({ value: o.value, label: o.textContent.trim(), disabled: o.disabled }));
            this.value = sel.value;
            if (this.searchable === null) this.searchable = this.options.length > 5;
            this.sync();
        },
        sync() {
            const o = this.options.find(o => o.value === this.value);
            this.label = o ? o.label : '';
        },
        get filtered() {
            if (!this.query) return this.options;
            const q = this.norm(this.query);
            return this.options.filter(o => this.norm(o.label).includes(q));
        },
        pick(v) {
            const sel = this.$refs.native;
            sel.value = v;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            this.value = v;
            this.sync();
            this.open = false;
            this.query = '';
        },
    }"
    x-on:keydown.escape="open = false"
    x-on:click.outside="open = false"
    class="relative w-full {{ $attributes->get('class') }}"
>
    {{-- Real form element: invisible overlay, still focusable for native validation. --}}
    <select
        x-ref="native"
        name="{{ $name }}"
        id="{{ $id }}"
        tabindex="-1"
        x-on:change="value = $refs.native.value; sync()"
        class="absolute inset-0 h-full w-full opacity-0 pointer-events-none"
        {{ $attributes->except('class') }}
    >
        {{ $slot }}
    </select>

    {{-- Visible trigger --}}
    <button
        type="button"
        x-on:click="open = !open"
        class="flex w-full items-center justify-between gap-2 border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sage-400"
    >
        <span x-text="label || @js($placeholder ?? __('site.form_select_placeholder'))" :class="{ 'text-gray-400': !label }" class="truncate text-start"></span>
        <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>

    {{-- Dropdown --}}
    <div x-show="open" x-cloak x-transition.opacity class="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg">
        <div x-show="searchable" class="flex items-center gap-2 border-b border-gray-100 px-2">
            <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 17a6 6 0 100-12 6 6 0 000 12z"/></svg>
            <input x-model="query" type="text" placeholder="@lang('site.form_search')" class="h-10 w-full bg-transparent text-sm outline-none">
        </div>
        <ul class="max-h-60 overflow-auto py-1">
            <template x-for="o in filtered" :key="o.value">
                <li>
                    <button type="button" x-on:click="pick(o.value)" :disabled="o.disabled"
                            class="flex w-full items-center justify-between gap-2 px-3 py-2 text-start text-sm hover:bg-gray-100 disabled:opacity-50"
                            :class="{ 'bg-gray-100 font-medium': o.value === value }">
                        <span class="truncate" x-text="o.label"></span>
                    </button>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-gray-400">@lang('site.form_no_results')</li>
        </ul>
    </div>
</div>
