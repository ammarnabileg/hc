@props(['screen' => null])

@php
    use App\Services\Ui\FirstRunScreens;

    /*
     | ⭐ «شاشة أوّل مرّة» — على أهمّ الشاشات فقط منعًا للزحام (2.15-د).
     |
     | المحتوى **من جدول `onboarding_slides`** أيْ ممّا كتبه الأدمن بالضبط في
     | شاشته: العنوان والنصّ والصورة وزرّ الإجراء وترتيب المراحل. فما يراه في
     | المعاينة هو ما يراه المستخدم — لا نسخة ثانية تُكتَب في مكان آخر.
     */
    $screenKey = $screen ?: request()->route()?->getName();
    $service = app(FirstRunScreens::class);
    $steps = $screenKey ? $service->stepsFor($screenKey) : [];
    $enabled = $screenKey && $service->isEnabled($screenKey) && $steps !== [];
    $show = $enabled && $service->shouldShow(auth()->user(), (string) $screenKey);
    $labels = $service->labels();
    $first = $steps[0] ?? ['title' => '', 'body' => '', 'image_url' => null, 'action_label' => null, 'action_url' => null];
@endphp

@if ($enabled)
    <div data-first-run="{{ $screenKey }}" data-first-run-open="{{ $show ? '1' : '0' }}"
         class="fixed inset-0 z-[55] {{ $show ? 'flex' : 'hidden' }} items-center justify-center p-4"
         style="background: rgb(0 0 0 / .6)" role="dialog" aria-modal="true" aria-label="{{ setting('ux.first_run.aria_label_1', 'أوّل مرّة هنا') }}">
        <div class="card w-full max-w-md">
            <div class="px-5 py-4" style="border-bottom: 1px solid var(--border)">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-bold" data-first-run-title>{{ $first['title'] }}</h2>
                    <span class="text-xs" style="color: var(--text-muted)">
                        <span data-first-run-index>1</span>/{{ count($steps) }}
                    </span>
                </div>
            </div>

            {{-- صورة الشريحة إن رفعها الأدمن — تظهر وتختفي مع المرحلة --}}
            <img data-first-run-image alt="" @class(['w-full', 'hidden' => ! $first['image_url']])
                 src="{{ $first['image_url'] ?: '' }}"
                 style="max-height: 12rem; object-fit: cover">

            <div class="px-5 py-4 text-sm" data-first-run-body>{{ $first['body'] }}</div>

            <div class="px-5 py-4 flex flex-wrap items-center justify-between gap-2" style="border-top: 1px solid var(--border)">
                <button type="button" data-first-run-skip class="text-xs" style="color: var(--text-muted); min-height: 44px">{{ $labels['skip'] }}</button>

                <span class="flex items-center gap-2">
                    {{-- زرّ إجراء الشريحة: يوصل المستخدم للمكان الذي تتكلّم عنه --}}
                    <a data-first-run-action href="{{ $first['action_url'] ?: '#' }}"
                       @class(['btn rounded-xl px-3 text-xs motion-standard inline-flex items-center', 'hidden' => ! $first['action_url'] || ! $first['action_label']])
                       style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $first['action_label'] }}</a>

                    <button type="button" data-first-run-next
                            class="btn rounded-xl px-4 font-semibold motion-standard"
                            style="min-height: 44px; background: var(--color-brand-500); color: #04201c">{{ count($steps) > 1 ? $labels['next'] : $labels['done'] }}</button>
                </span>
            </div>
        </div>

        <script type="application/json" data-first-run-steps>@json($steps)</script>
        <script type="application/json" data-first-run-labels>@json($labels)</script>
    </div>

    {{-- زرّ «؟» يعيدها وقت ما شاء المستخدم (2.15-د) --}}
    <button type="button" data-first-run-replay="{{ $screenKey }}"
            class="fixed z-30 inline-flex items-center justify-center rounded-full motion-standard"
            style="inset-inline-start: 1rem; inset-block-end: 1rem; width: 44px; height: 44px;
                   background: var(--surface-raised); border: 1px solid var(--border); color: var(--text-muted)"
            aria-label="{{ $labels['replay'] }}" title="{{ $labels['replay'] }}">؟</button>
@endif
