@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-center">
        <ul class="inline-flex items-center gap-1">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li>
                    <span class="px-3 py-2 text-sm text-gray-400 rounded-lg cursor-default select-none">
                        <span class="rtl:hidden">&laquo;</span><span class="ltr:hidden">&raquo;</span>
                    </span>
                </li>
            @else
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                       class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-teal-50 hover:text-teal-700 transition-colors">
                        <span class="rtl:hidden">&laquo;</span><span class="ltr:hidden">&raquo;</span>
                    </a>
                </li>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <li>
                        <span class="px-3 py-2 text-sm text-gray-400 select-none">{{ $element }}</span>
                    </li>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li aria-current="page">
                                <span class="px-3.5 py-2 text-sm font-semibold text-white bg-teal-600 border border-teal-600 rounded-lg select-none">{{ $page }}</span>
                            </li>
                        @else
                            <li>
                                <a href="{{ $url }}"
                                   class="px-3.5 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-teal-50 hover:text-teal-700 transition-colors">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                       class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-teal-50 hover:text-teal-700 transition-colors">
                        <span class="rtl:hidden">&raquo;</span><span class="ltr:hidden">&laquo;</span>
                    </a>
                </li>
            @else
                <li>
                    <span class="px-3 py-2 text-sm text-gray-400 rounded-lg cursor-default select-none">
                        <span class="rtl:hidden">&raquo;</span><span class="ltr:hidden">&laquo;</span>
                    </span>
                </li>
            @endif
        </ul>
    </nav>
@endif
