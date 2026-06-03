{{--
    Secondary navigation shown beside the primary booking CTA on detail
    pages: "back" (browser history) + "browse the complex" (the parent
    clinic page). Expects: $clinic.
--}}
<button type="button" onclick="history.back()"
        class="inline-flex items-center justify-center gap-1.5 min-h-touch border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-800 font-semibold px-5 py-3 rounded-lg transition-colors">
    <span class="rtl:rotate-180" aria-hidden="true">←</span>
    @lang('site.detail_back')
</button>

<a href="{{ route('clinic.show', $clinic->slug) }}"
   class="inline-flex items-center justify-center gap-1.5 min-h-touch border border-sage-200 text-sage-700 hover:bg-sage-50 font-semibold px-5 py-3 rounded-lg transition-colors">
    <x-icon name="building" class="w-4 h-4" />
    @lang('site.detail_browse_complex')
</a>
