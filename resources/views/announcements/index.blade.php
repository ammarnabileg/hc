@extends('layouts.app')

@section('title', 'التعليمات')

@php
    /**
     * التعليمات (13.2 · 24.5): فيد بثّ اتّجاه واحد — بلا ردود.
     * سؤال واحد للشاشة: «إيه الجديد من الإدارة؟» — وفعل رئيسيّ واحد: تعليم الكلّ كمقروء.
     */
    $ackLabel = (string) setting('announcements.acknowledge.label', 'قرأتُ وفهمت');
    $emptyMessage = (string) setting('announcements.empty.message', 'لا تعليمات جديدة');
    $subtitle = $unread > 0 ? 'عندك '.$unread.' منشور لسّه ما اتقروش' : 'كلّ التعليمات مقروءة — تمام ✓';
@endphp

@section('content')
    <x-page-header
        title="التعليمات"
        :subtitle="$subtitle"
        :breadcrumbs="[['label' => 'الرئيسيّة', 'url' => route('dashboard')], ['label' => 'التعليمات']]">
        <x-slot:action>
            @if ($unread > 0)
                <form method="post" action="{{ route('announcements.read-all') }}" data-ajax-form>
                    @csrf
                    <button type="submit"
                            class="btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">
                        تعليم الكلّ كمقروء
                        <span class="rounded-full px-2 text-xs" style="background: rgb(0 0 0 / .15)">{{ $unread }}</span>
                    </button>
                </form>
            @endif
        </x-slot:action>
    </x-page-header>

    {{-- ثلاثة فلاتر ظاهرة فقط: غير المقروء · النوع · بحث (2.15-أ-4) --}}
    <x-filters :action="route('announcements.index')">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="unread" value="1" @checked($filters['unread'])
                   onchange="this.form.submit()" style="accent-color: var(--color-brand-500)">
            غير المقروء
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">النوع</span>
            <select name="type" onchange="this.form.submit()"
                    class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="ابحث بالعنوان…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($items->isEmpty())
        <x-empty :message="$emptyMessage" action="الرجوع للرئيسيّة" :href="route('dashboard')" />
    @else
        <div class="space-y-4">
            @foreach ($items as $announcement)
                @include('announcements.partials.card', [
                    'announcement' => $announcement,
                    'read' => $reads[$announcement->id] ?? null,
                    'counts' => $reactionCounts[$announcement->id] ?? [],
                    'reactions' => $reactions,
                    'ackLabel' => $ackLabel,
                ])
            @endforeach
        </div>

        @if ($hasMore)
            {{-- تمرير تدريجيّ لا ترقيم صفحات (2.15 — الترقيم مرفوض) --}}
            <div class="mt-5 text-center">
                <a class="btn inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm motion-standard"
                   style="background: var(--surface-raised); border: 1px solid var(--border)"
                   href="{{ route('announcements.index', array_filter($filters) + ['more' => $nextMore]) }}">
                    عرض المزيد ({{ $total - $items->count() }})
                </a>
            </div>
        @endif
    @endif

    {{-- بوب-أب الإقرار الإلزاميّ للمنشورات الحرجة — وعلى الموبايل Bottom Sheet (2.15-ج) --}}
    @if ($pendingAck)
        <x-modal id="ack-modal" :title="$pendingAck->title">
            <p class="text-sm leading-7 whitespace-pre-line">{{ $pendingAck->body }}</p>
            @if ($pendingAck->cta_url)
                <a href="{{ $pendingAck->cta_url }}" target="_blank" rel="noopener"
                   class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm mt-4 motion-standard"
                   style="background: var(--surface-sunken)">{{ $pendingAck->cta_label ?: 'افتح الرابط' }}</a>
            @endif

            <x-slot:footer>
                <form method="post" action="{{ route('announcements.acknowledge', $pendingAck) }}" class="flex justify-end">
                    @csrf
                    <button type="submit"
                            class="btn inline-flex items-center justify-center rounded-xl px-5 py-2 text-sm font-bold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ $ackLabel }}</button>
                </form>
            </x-slot:footer>
        </x-modal>
    @endif

    {{-- تفاصيل المنشور: بانل/بوب-أب لا صفحة جديدة (2.15-أ-6) — Bottom Sheet على الموبايل --}}
    <x-modal id="announcement-sheet" title="تفاصيل المنشور">
        <h3 class="font-bold mb-2" data-sheet-title></h3>
        <p class="text-sm leading-7 whitespace-pre-line" data-sheet-body></p>
        <a href="#" target="_blank" rel="noopener" data-sheet-cta
           class="btn hidden items-center rounded-xl px-4 py-2 text-sm mt-4 motion-standard"
           style="background: var(--surface-sunken)"></a>
    </x-modal>
@endsection

@section('mobile_action')
    @if ($unread > 0)
        <form method="post" action="{{ route('announcements.read-all') }}">
            @csrf
            <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">تعليم الكلّ كمقروء ({{ $unread }})</button>
        </form>
    @endif
@endsection

@push('head')
    <style>
        /* على الموبايل: البوب-أب يصير Bottom Sheet برأس ثابت وجسم متمرّر (2.15-ج) */
        @media (max-width: 767px) {
            #ack-modal, #announcement-sheet { padding: 0; }
            #ack-modal .modal-shell, #announcement-sheet .modal-shell {
                align-self: flex-end;
                max-block-size: 85vh;
                border-end-start-radius: 0;
                border-end-end-radius: 0;
            }
        }
        .announcement-body { display: -webkit-box; -webkit-line-clamp: 6; -webkit-box-orient: vertical; overflow: hidden; }
    </style>
@endpush

@push('scripts')
    <script>
        // فتح الإقرار الإلزاميّ تلقائيًّا قبل المتابعة (13.2)
        const ack = document.getElementById('ack-modal');
        if (ack) { ack.classList.remove('hidden'); ack.classList.add('flex'); }

        // تفاصيل المنشور في بوب-أب/Bottom Sheet بدل صفحة جديدة
        const sheet = document.getElementById('announcement-sheet');
        document.querySelectorAll('[data-announcement-details]').forEach((btn) => {
            btn.addEventListener('click', () => {
                sheet.querySelector('[data-sheet-title]').textContent = btn.dataset.title || '';
                sheet.querySelector('[data-sheet-body]').textContent = btn.dataset.body || '';
                const cta = sheet.querySelector('[data-sheet-cta]');
                if (btn.dataset.ctaUrl) {
                    cta.href = btn.dataset.ctaUrl;
                    cta.textContent = btn.dataset.ctaLabel || 'افتح الرابط';
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

        /*
         | ردّ فوريّ لكلّ فعل (2.17-ب): الفعل يظهر فورًا، ولو فشل الخادم
         | نُرجّع الصفحة لحالتها الصحيحة بإعادة التحميل بدل ترك المستخدم في شكّ.
         */
        document.querySelectorAll('[data-ajax-form]').forEach((form) => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const card = form.closest('[data-announcement]');
                if (card) card.removeAttribute('data-unread');

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('failed');
                    window.location.reload();
                } catch {
                    window.location.reload();
                }
            });
        });
    </script>
@endpush
