@props(['screen' => null])

@php
    use App\Models\SavedView;

    /*
     | ⭐ العروض المحفوظة (2.15-د): «أيّ تركيبة فلاتر تُحفَظ بضغطة كعرض محفوظ
     | وتظهر **كرقاقة فوق الجدول**» — فتقلّ القرارات ولا يعيد المستخدم ضبط
     | الفلاتر كلّ مرّة.
     */
    $screenKey = $screen ?: request()->route()?->getName();
    $viewer = auth()->user();

    $views = $viewer && $screenKey
        ? SavedView::query()
            ->where('user_id', $viewer->id)
            ->where('screen', $screenKey)
            ->orderBy('sort_order')
            ->get()
        : collect();

    // الفلاتر الحاليّة = كويري الصفحة بلا ضجيج الترقيم
    $currentFilters = collect(request()->query())->except(['page', 'saved_view'])->all();
    $activeKey = json_encode($currentFilters, JSON_UNESCAPED_UNICODE);
@endphp

@if ($viewer && $screenKey)
    <div class="flex flex-wrap items-center gap-2 mb-3">
        @foreach ($views as $view)
            @php $isActive = json_encode((array) $view->filters, JSON_UNESCAPED_UNICODE) === $activeKey; @endphp

            <span class="inline-flex items-center rounded-full overflow-hidden"
                  style="background: {{ $isActive ? 'var(--color-brand-500)' : 'var(--surface-raised)' }};
                         color: {{ $isActive ? '#04201c' : 'var(--text)' }}">
                <a href="{{ $view->url() }}" class="px-3 text-sm inline-flex items-center"
                   style="min-height: 44px">{{ $view->name }}</a>

                <form method="post" action="{{ route('ui.views.destroy', $view) }}" class="inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-2 text-xs opacity-70 hover:opacity-100"
                            style="min-width: 44px; min-height: 44px"
                            aria-label="{{ setting('ux.saved_views.aria_label_1', 'امسح العرض') }} {{ $view->name }}">✕</button>
                </form>
            </span>
        @endforeach

        @if ($currentFilters !== [])
            <details class="inline-block">
                <summary class="cursor-pointer rounded-full px-3 text-sm inline-flex items-center"
                         style="min-height: 44px; background: var(--surface-sunken); color: var(--text-muted)">{{ setting('ux.saved_views.text_1', 'احفظ العرض') }}</summary>

                <form method="post" action="{{ route('ui.views.store') }}" class="card p-3 mt-2 flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="screen" value="{{ $screenKey }}">
                    @foreach ($currentFilters as $key => $value)
                        <input type="hidden" name="filters[{{ $key }}]" value="{{ is_array($value) ? implode(',', $value) : $value }}">
                    @endforeach

                    <label class="block">
                        <span class="block text-xs mb-1">{{ setting('ux.saved_views.text_2', 'اسم العرض') }}</span>
                        <input type="text" name="name" maxlength="96" required placeholder="{{ setting('ux.saved_views.placeholder_1', 'مثلًا: محافظتي — آخر 7 أيّام') }}"
                               class="rounded-xl px-3 text-sm" style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>

                    <button type="submit" class="btn rounded-xl px-4 text-sm font-semibold motion-standard"
                            style="min-height: 44px; background: var(--color-brand-500); color: #04201c">{{ setting('ux.saved_views.text_3', 'احفظ') }}</button>
                </form>
            </details>
        @endif
    </div>
@endif
