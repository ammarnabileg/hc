@props(['screen' => null])

@php
    use App\Services\Ui\FirstRunScreens;

    /*
     | ⭐ «شاشة أوّل مرّة» — على أهمّ الشاشات فقط منعًا للزحام (2.15-د).
     | الأدمن يختار الشاشات وماذا يظهر بالضبط، **وزرّ «؟» يعيدها وقت ما شاء**.
     */
    $screenKey = $screen ?: request()->route()?->getName();
    $service = app(FirstRunScreens::class);
    $steps = $screenKey ? $service->stepsFor($screenKey) : [];
    $enabled = $screenKey && $service->isEnabled($screenKey) && $steps !== [];
    $show = $enabled && $service->shouldShow(auth()->user(), (string) $screenKey);
@endphp

@if ($enabled)
    <div data-first-run="{{ $screenKey }}" data-first-run-open="{{ $show ? '1' : '0' }}"
         class="fixed inset-0 z-[55] {{ $show ? 'flex' : 'hidden' }} items-center justify-center p-4"
         style="background: rgb(0 0 0 / .6)" role="dialog" aria-modal="true" aria-label="أوّل مرّة هنا">
        <div class="card w-full max-w-md">
            <div class="px-5 py-4" style="border-bottom: 1px solid var(--border)">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-bold" data-first-run-title>{{ $steps[0]['title'] ?? '' }}</h2>
                    <span class="text-xs" style="color: var(--text-muted)">
                        <span data-first-run-index>1</span>/{{ count($steps) }}
                    </span>
                </div>
            </div>

            <div class="px-5 py-4 text-sm" data-first-run-body>{{ $steps[0]['body'] ?? '' }}</div>

            <div class="px-5 py-4 flex items-center justify-between gap-2" style="border-top: 1px solid var(--border)">
                <button type="button" data-first-run-skip class="text-xs" style="color: var(--text-muted); min-height: 44px">تخطّي</button>
                <button type="button" data-first-run-next
                        class="btn rounded-xl px-4 font-semibold motion-standard"
                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">التالي</button>
            </div>
        </div>

        <script type="application/json" data-first-run-steps>@json($steps)</script>
    </div>

    {{-- زرّ «؟» يعيدها وقت ما شاء المستخدم (2.15-د) --}}
    <button type="button" data-first-run-replay="{{ $screenKey }}"
            class="fixed z-30 inline-flex items-center justify-center rounded-full motion-standard"
            style="inset-inline-start: 1rem; inset-block-end: 1rem; width: 44px; height: 44px;
                   background: var(--surface-raised); border: 1px solid var(--border); color: var(--text-muted)"
            aria-label="عيد شرح الصفحة" title="عيد شرح الصفحة">؟</button>
@endif
