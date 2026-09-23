@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="disabled" aria-hidden="true"><x-icon name="chevron-left" :size="16" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous"><x-icon name="chevron-left" :size="16" /></a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="gap">…</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="current" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next"><x-icon name="chevron-right" :size="16" /></a>
        @else
            <span class="disabled" aria-hidden="true"><x-icon name="chevron-right" :size="16" /></span>
        @endif
    </nav>
@endif
