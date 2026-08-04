@extends('layouts.app')
@section('title', setting('account.search.title', 'البحث'))

@section('content')
    <x-page-header
        :title="setting('account.search.title', 'البحث')"
        :subtitle="setting('account.search.subtitle', 'ادخل على أيّ حدّ من الكود أو الاسم.')"
        :breadcrumbs="[['label' => setting('account.search.title', 'البحث')]]" />

    <form method="get" action="{{ route('search') }}" class="card p-4 mb-4" data-search-form>
        <div class="flex gap-2">
            <input type="search" name="q" value="{{ $q }}" autofocus placeholder="{{ setting('account.search.placeholder', 'اكتب كود أو اسم أو بريد أو رقم موبايل') }}"
                   class="flex-1 rounded-xl px-4 py-3 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                   aria-label="{{ setting('account.search.input_aria', 'كلمة البحث') }}">
            <button type="submit" class="btn rounded-xl px-5 py-3 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('account.search.submit', 'إبحث') }}</button>
        </div>

        {{--
          ⭐ Checkboxes بمنطق «الكلّ» الحصريّ (13.1):
          اختيار «الكلّ» يلغي الباقي، واختيار أيّ حقل محدَّد يلغي «الكلّ».
          والحقل المخفيّ `last` يحمل آخر مربّع ضُغِط ليحسم الخادمُ التعارضَ كذلك.
        --}}
        <input type="hidden" name="last" value="{{ request('last') }}" data-search-last>

        <div class="mt-3 flex flex-wrap gap-3">
            @foreach ($fieldLabels as $key => $label)
                <label class="flex items-center gap-2 text-sm cursor-pointer rounded-xl px-3 py-2"
                       style="background: var(--surface-sunken); border: 1px solid var(--border)">
                    <input type="checkbox" name="fields[]" value="{{ $key }}"
                           data-search-field="{{ $key }}"
                           @checked(in_array($key, $fields, true))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>

        <p class="text-xs mt-3" style="color: var(--text-muted)">
            {{ setting('account.search.privacy_note', 'البحث بالبريد أو رقم الموبايل وسيلة وصول بس — النتيجة بتفتح البروفايل العامّ ومفيش أيّ بيانات حسّاسة.') }}
        </p>
    </form>

    @if ($q === '')
        <x-empty :message="setting('account.search.empty_idle', 'اكتب كلمة وابدأ البحث.')" />
    @elseif ($total === 0)
        <x-empty :message="setting('account.search.empty_no_results', 'مفيش نتائج — جرّب كود أو اسم تاني.')" />
    @else
        <p class="text-xs mb-3" style="color: var(--text-muted)">{{ str_replace(':total', $total, (string) setting('account.search.results_count', ':total نتيجة')) }}</p>

        <div class="grid md:grid-cols-2 gap-3" data-search-results>
            @include('account.partials.search-results')
        </div>

        {{-- Skeleton بشكل الكارت الحقيقيّ لا مستطيل عامّ (2.15-د) --}}
        <div class="grid md:grid-cols-2 gap-3 mt-3" data-search-skeleton hidden aria-hidden="true">
            @for ($i = 0; $i < 2; $i++)
                <div class="card p-4 flex items-center gap-3">
                    <span class="rounded-full animate-shimmer" style="width:2.5rem;height:2.5rem;background: var(--surface-sunken)"></span>
                    <span class="flex-1">
                        <span class="block h-3 rounded animate-shimmer mb-2" style="width:60%;background: var(--surface-sunken)"></span>
                        <span class="block h-3 rounded animate-shimmer" style="width:40%;background: var(--surface-sunken)"></span>
                    </span>
                </div>
            @endfor
        </div>

        @if ($hasMore)
            {{-- تمرير تدريجيّ 6 في المرّة — وبلا JS يبقى زرّ يعمل (2.1 · 13.1) --}}
            <div class="text-center mt-4">
                <a href="{{ route('search.more', array_merge(request()->only('q', 'fields', 'last'), ['offset' => $nextOffset])) }}"
                   data-search-more data-next-offset="{{ $nextOffset }}"
                   class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm motion-standard"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('account.search.load_more', 'عرض المزيد') }}</a>
            </div>
        @endif
    @endif
@endsection

@push('scripts')
    <script>
        /* ---------------------------------------------------------------
         | ⭐ «الكلّ» حصريّ (13.1): اختياره يلغي الباقي، والعكس بالعكس.
         --------------------------------------------------------------- */
        const lastInput = document.querySelector('[data-search-last]');
        const boxes = document.querySelectorAll('[data-search-field]');

        boxes.forEach((box) => {
            box.addEventListener('change', () => {
                const key = box.dataset.searchField;
                if (lastInput) lastInput.value = key;

                if (key === 'all') {
                    if (box.checked) {
                        boxes.forEach((b) => { if (b.dataset.searchField !== 'all') b.checked = false; });
                    }
                    return;
                }

                if (box.checked) {
                    const all = document.querySelector('[data-search-field="all"]');
                    if (all) all.checked = false;
                }
            });
        });

        /* ---------------------------------------------------------------
         | تمرير تدريجيّ: 6 في المرّة مع Skeleton أثناء التحميل (13.1)
         --------------------------------------------------------------- */
        const results = document.querySelector('[data-search-results]');
        const skeleton = document.querySelector('[data-search-skeleton]');
        let button = document.querySelector('[data-search-more]');
        let loading = false;

        const loadMore = async () => {
            if (!button || loading) return;
            loading = true;
            if (skeleton) skeleton.hidden = false;

            try {
                const res = await fetch(button.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const html = await res.text();
                results?.insertAdjacentHTML('beforeend', html);

                const next = Number(button.dataset.nextOffset) + {{ $pageSize }};
                const url = new URL(button.href, window.location.origin);
                url.searchParams.set('offset', String(next));
                button.href = url.toString();
                button.dataset.nextOffset = String(next);

                // انتهت النتائج: الزرّ يختفي بدل أن يبقى بلا فائدة (2.15-أ-7)
                if (!html.trim()) {
                    button.remove();
                    button = null;
                }
            } catch {
                /* الشبكة اتقطعت — الزرّ فاضل مكانه ويقدر يجرّب تاني */
            } finally {
                if (skeleton) skeleton.hidden = true;
                loading = false;
            }
        };

        button?.addEventListener('click', (e) => { e.preventDefault(); loadMore(); });

        if ('IntersectionObserver' in window && button) {
            new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) loadMore();
            }, { rootMargin: '200px' }).observe(button);
        }
    </script>
@endpush
