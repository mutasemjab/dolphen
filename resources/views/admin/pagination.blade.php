@if ($paginator->hasPages())
    <nav class="pagination" aria-label="{{ __('erp.pagination') }}">
        @if (!$paginator->onFirstPage())
            <a class="btn small" href="{{ $paginator->previousPageUrl() }}">{{ __('erp.previous') }}</a>
        @endif
        <span>
            {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }} · {{ $paginator->total() }}
            {{ __('erp.records') }}</span>
        @if ($paginator->hasMorePages())
            <a class="btn small" href="{{ $paginator->nextPageUrl() }}">{{ __('erp.next') }}</a>
        @endif
    </nav>
@endif
