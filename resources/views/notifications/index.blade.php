@php
    /** نصوص السكربت — من الإعدادات لا محروقةً في الجافاسكربت (2.13-أ) */
    $hcWords = array_merge($hcWords ?? [], [
        'notifications.index.js_1' => (string) setting('notifications.index.js_1', 'افتح'),
    ]);
@endphp

@extends('layouts.app')

@section('title', (string) setting('notifications.index.section_1', 'الإشعارات'))

@php
    /**
     * مركز الإشعارات — الصفحة الكاملة (2.8).
     * سؤال واحد للشاشة: «إيه اللي حصل ويخصّني؟» — وفعل رئيسيّ واحد: تعليم الكلّ كمقروء.
     */
    $emptyMessage = (string) setting('notifications.empty.message', 'مفيش إشعارات جديدة');
    $subtitle = $unread > 0 ? strtr((string) setting('notifications.index.php_1', 'عندك :a1 إشعار غير مقروء'), [':a1' => (string) ($unread)]) : (string) setting('notifications.index.php_2', 'مفيش غير مقروء — تمام ✓');
@endphp

@section('content')
    <x-page-header
        title="{{ setting('notifications.index.title_1', 'الإشعارات') }}"
        :subtitle="$subtitle"
        :breadcrumbs="[['label' => (string) setting('notifications.index.breadcrumbs_1', 'الرئيسيّة'), 'url' => route('dashboard')], ['label' => (string) setting('notifications.index.breadcrumbs_2', 'الإشعارات')]]">
        <x-slot:action>
            @if ($unread > 0)
                <form method="post" action="{{ route('notifications.read-all') }}" data-ajax-form>
                    @csrf
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <button type="submit"
                            class="btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">
                        {{ setting('notifications.index.text_1', 'تعليم الكلّ كمقروء') }}
                        <span class="rounded-full px-2 text-xs" style="background: rgb(0 0 0 / .15)">{{ $unread }}</span>
                    </button>
                </form>
            @endif
        </x-slot:action>
    </x-page-header>

    {{-- ثلاثة تابات: الكلّ · المنصّة · التطوّع — وعلى الموبايل رقائق أفقيّة (2.8 · 2.15-ج) --}}
    <x-tabs :tabs="$tabs" :current="$tab" />

    @if (empty($groups))
        <x-empty :message="$emptyMessage" action="{{ setting('notifications.index.action_1', 'الرجوع للرئيسيّة') }}" :href="route('dashboard')" />
    @else
        <div class="space-y-6">
            @foreach ($groups as $label => $rows)
                <section>
                    <h2 class="text-xs mb-2 px-1" style="color: var(--text-muted)">{{ $label }}</h2>
                    <div class="card divide-y" style="border-color: var(--border)">
                        @foreach ($rows as $notification)
                            @include('notifications.partials.row', [
                                'notification' => $notification,
                                'actionLabel' => $actionLabel,
                            ])
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
            @if ($hasMore)
                <a class="btn inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm motion-standard"
                   style="background: var(--surface-raised); border: 1px solid var(--border)"
                   href="{{ route('notifications.index', array_filter(['tab' => $tab, 'range' => $expanded ? 'all' : null, 'more' => $nextMore])) }}">
                    {{ setting('notifications.index.text_2', 'عرض المزيد') }}
                </a>
            @endif

            @unless ($expanded)
                {{-- المدى الافتراضيّ آخر 30 يومًا مع زرّ «وسّع المدى» (2.15-ب) --}}
                <a class="text-xs" style="color: var(--color-brand-500)"
                   href="{{ route('notifications.index', array_filter(['tab' => $tab, 'range' => 'all'])) }}">
                    {{ strtr((string) setting('notifications.index.text_3', 'وسّع المدى (أقدم من :a1 يومًا)'), [':a1' => (string) ($rangeDays)]) }}
                </a>
            @endunless
        </div>
    @endif

    {{-- التفاصيل في بوب-أب لا صفحة جديدة — وعلى الموبايل Bottom Sheet (2.15-أ-6 · 2.15-ج) --}}
    <x-modal id="notification-sheet" title="{{ setting('notifications.index.title_2', 'تفاصيل الإشعار') }}">
        <h3 class="font-bold mb-2" data-sheet-title></h3>
        <p class="text-sm leading-7 whitespace-pre-line" data-sheet-body></p>
        <a href="#" data-sheet-cta
           class="btn hidden items-center rounded-xl px-4 py-2 text-sm mt-4 font-semibold motion-standard"
           style="background: var(--color-brand-500); color: #04201c"></a>
    </x-modal>
@endsection

@section('mobile_action')
    @if ($unread > 0)
        <form method="post" action="{{ route('notifications.read-all') }}">
            @csrf
            <input type="hidden" name="tab" value="{{ $tab }}">
            <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ strtr((string) setting('notifications.index.text_4', 'تعليم الكلّ كمقروء (:a1)'), [':a1' => (string) ($unread)]) }}</button>
        </form>
    @endif
@endsection

@push('head')
    <style>
        @media (max-width: 767px) {
            #notification-sheet { padding: 0; }
            #notification-sheet .modal-shell {
                align-self: flex-end;
                max-block-size: 85vh;
                border-end-start-radius: 0;
                border-end-end-radius: 0;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        const sheet = document.getElementById('notification-sheet');
        document.querySelectorAll('[data-notification-details]').forEach((btn) => {
            btn.addEventListener('click', () => {
                sheet.querySelector('[data-sheet-title]').textContent = btn.dataset.title || '';
                sheet.querySelector('[data-sheet-body]').textContent = btn.dataset.body || '';
                const cta = sheet.querySelector('[data-sheet-cta]');
                if (btn.dataset.url) {
                    cta.href = btn.dataset.url;
                    cta.textContent = btn.dataset.actionLabel || @json($hcWords['notifications.index.js_1']);
                    cta.classList.remove('hidden');
                    cta.classList.add('inline-flex');
                } else {
                    cta.classList.add('hidden');
                    cta.classList.remove('inline-flex');
                }
                sheet.classList.remove('hidden');
                sheet.classList.add('flex');
            });
        });

        // ردّ فوريّ لكلّ فعل (2.17-ب) — ولو فشل الخادم نعيد الصفحة لحالتها الصحيحة
        document.querySelectorAll('[data-ajax-form]').forEach((form) => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const row = form.closest('[data-notification]');
                if (row) row.removeAttribute('data-unread');

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('failed');
                } finally {
                    window.location.reload();
                }
            });
        });
    </script>
@endpush
