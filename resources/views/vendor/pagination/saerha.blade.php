@if ($paginator->hasPages())
    {{--
        Responsive pagination:
          - flex-wrap on the ul so 10+ page links don't push horizontal
            scroll on a 375px phone.
          - Numbered links collapse on xs and only re-appear at sm+ —
            on mobile we surface prev/next + a compact "page X of Y"
            indicator instead, which keeps the band short enough not
            to wrap to a second row.
          - Every interactive cell hits min-h-touch / min-w-touch
            (44×44) so a thumb tap doesn't miss.
    --}}
    @php
        $btnBase = 'inline-flex items-center justify-center min-h-touch min-w-touch px-3 text-sm rounded-lg border transition-colors';
        $btnIdle = 'text-gray-700 bg-white border-gray-200 hover:bg-sage-50 hover:text-sage-700';
        $btnDisabled = 'text-gray-400 bg-white border-gray-100 cursor-default select-none';
        $btnActive = 'text-white bg-sage-600 border-sage-600 font-semibold select-none';
    @endphp
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-center">
        <ul class="flex flex-wrap items-center justify-center gap-1">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <li>
                    <span class="{{ $btnBase }} {{ $btnDisabled }}" aria-hidden="true">
                        <span class="rtl:hidden">&laquo;</span><span class="ltr:hidden">&raquo;</span>
                    </span>
                </li>
            @else
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                       aria-label="{{ __('pagination.previous') }}"
                       class="{{ $btnBase }} {{ $btnIdle }}">
                        <span class="rtl:hidden">&laquo;</span><span class="ltr:hidden">&raquo;</span>
                    </a>
                </li>
            @endif

            {{-- Compact mobile indicator: "page X / Y" — shown on xs, hidden from sm.
                 Replaces the long number band so 11 page links don't overflow. --}}
            <li class="sm:hidden">
                <span class="{{ $btnBase }} {{ $btnActive }} px-4 whitespace-nowrap" dir="ltr">
                    {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
                </span>
            </li>

            {{-- Numbered links — hidden on xs, visible from sm. --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <li class="hidden sm:inline-flex">
                        <span class="{{ $btnBase }} {{ $btnDisabled }}">{{ $element }}</span>
                    </li>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="hidden sm:inline-flex" aria-current="page">
                                <span class="{{ $btnBase }} {{ $btnActive }}">{{ $page }}</span>
                            </li>
                        @else
                            <li class="hidden sm:inline-flex">
                                <a href="{{ $url }}" class="{{ $btnBase }} {{ $btnIdle }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                       aria-label="{{ __('pagination.next') }}"
                       class="{{ $btnBase }} {{ $btnIdle }}">
                        <span class="rtl:hidden">&raquo;</span><span class="ltr:hidden">&laquo;</span>
                    </a>
                </li>
            @else
                <li>
                    <span class="{{ $btnBase }} {{ $btnDisabled }}" aria-hidden="true">
                        <span class="rtl:hidden">&raquo;</span><span class="ltr:hidden">&laquo;</span>
                    </span>
                </li>
            @endif
        </ul>
    </nav>
@endif
