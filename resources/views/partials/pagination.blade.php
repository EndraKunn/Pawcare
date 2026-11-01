@if ($paginator->hasPages())
  <nav class="pg" role="navigation" aria-label="Pagination">
    @if ($paginator->onFirstPage())
      <span>‹ Sebelumnya</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Sebelumnya</a>
    @endif
    <span class="cur">Halaman {{ $paginator->currentPage() }}@if(method_exists($paginator, 'lastPage')) dari {{ $paginator->lastPage() }}@endif</span>
    @if ($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya ›</a>
    @else
      <span>Berikutnya ›</span>
    @endif
  </nav>
@endif
