@php use App\Support\Persian; @endphp
@if ($paginator->hasPages())
<nav class="sc-pager" role="navigation" aria-label="صفحه‌بندی">
    @if (method_exists($paginator, 'total'))
    <span class="muted fs12">نمایش {{ Persian::digits($paginator->firstItem()) }} تا {{ Persian::digits($paginator->lastItem()) }} از {{ Persian::digits($paginator->total()) }}</span>
    @endif
    <div class="flex gap-8" style="flex-wrap:wrap">
        @if ($paginator->onFirstPage())
        <span class="btn btn-sm is-disabled" aria-disabled="true">قبلی</span>
        @else
        <a class="btn btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">قبلی</a>
        @endif

        @isset($elements)
        @foreach ($elements as $element)
            @if (is_string($element))
            <span class="btn btn-sm is-disabled" aria-disabled="true">…</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                    <span class="btn btn-sm btn-primary" aria-current="page">{{ Persian::digits($page) }}</span>
                    @else
                    <a class="btn btn-sm" href="{{ $url }}">{{ Persian::digits($page) }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @endisset

        @if ($paginator->hasMorePages())
        <a class="btn btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">بعدی</a>
        @else
        <span class="btn btn-sm is-disabled" aria-disabled="true">بعدی</span>
        @endif
    </div>
</nav>
<style>
.sc-pager{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.sc-pager .btn{min-width:36px;justify-content:center;text-decoration:none}
.sc-pager .is-disabled{opacity:.45;pointer-events:none}
</style>
@endif
