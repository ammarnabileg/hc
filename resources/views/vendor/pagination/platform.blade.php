{{--
    ترقيم الصفحات بنظام تصميم المنصّة — بديلٌ عن قالب Laravel الافتراضيّ الذي كان
    يرسم «Showing 1 to 10 of 50 results» بالإنجليزيّة ومفاتيح `pagination.previous`
    حرفيّةً ورماديّات Tailwind غير مُجمَّعة أصلًا (فيغيب المظهر الداكن).
    كلّ النصوص من `setting()` (2.13)، والأهداف 44px، وعلى الموبايل السابق/التالي
    والصفحة الحاليّة فقط. يُختار من AppServiceProvider (`Paginator::defaultView`).
--}}
@if ($paginator->hasPages())
    @php
        $isLengthAware = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
        $pageAria = (string) setting('ux.pagination.page_aria', 'الصفحة :n');
    @endphp
    <nav role="navigation" aria-label="{{ setting('ux.pagination.aria', 'التنقّل بين الصفحات') }}"
         class="pagination flex flex-wrap items-center justify-between gap-3">
        @if ($isLengthAware && $paginator->firstItem())
            <p class="text-xs m-0" style="color: var(--text-muted)">
                {{ strtr((string) setting('ux.pagination.summary', 'من :from إلى :to من أصل :total'), [
                    ':from' => $paginator->firstItem(),
                    ':to' => $paginator->lastItem(),
                    ':total' => $paginator->total(),
                ]) }}
            </p>
        @endif

        <ul class="flex items-center gap-1 m-0 p-0 list-none">
            {{-- السابق (يمين في RTL) --}}
            <li>
                @if ($paginator->onFirstPage())
                    <span class="pagination-btn is-disabled" aria-disabled="true" aria-label="{{ setting('ux.pagination.previous', 'السابق') }}">
                        <x-icon name="arrow" size="18" class="pagination-flip" />
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pagination-btn" aria-label="{{ setting('ux.pagination.previous', 'السابق') }}">
                        <x-icon name="arrow" size="18" class="pagination-flip" />
                    </a>
                @endif
            </li>

            @if ($isLengthAware)
                @foreach ($elements ?? [] as $element)
                    @if (is_string($element))
                        <li class="pagination-page" aria-hidden="true"><span class="pagination-dots">…</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="pagination-page is-current">
                                    <span class="pagination-btn is-current" aria-current="page" aria-label="{{ strtr($pageAria, [':n' => $page]) }}">{{ $page }}</span>
                                </li>
                            @else
                                <li class="pagination-page">
                                    <a href="{{ $url }}" class="pagination-btn" aria-label="{{ strtr($pageAria, [':n' => $page]) }}">{{ $page }}</a>
                                </li>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            @else
                <li class="pagination-page is-current">
                    <span class="pagination-btn is-current" aria-current="page">{{ $paginator->currentPage() }}</span>
                </li>
            @endif

            {{-- التالي (يسار في RTL) --}}
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pagination-btn" aria-label="{{ setting('ux.pagination.next', 'التالي') }}">
                        <x-icon name="arrow" size="18" />
                    </a>
                @else
                    <span class="pagination-btn is-disabled" aria-disabled="true" aria-label="{{ setting('ux.pagination.next', 'التالي') }}">
                        <x-icon name="arrow" size="18" />
                    </span>
                @endif
            </li>
        </ul>
    </nav>
@endif
