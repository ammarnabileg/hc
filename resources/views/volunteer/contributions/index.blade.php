@extends('layouts.app')

@section('title', 'مساهماتي')

@php
    /**
     * «مساهماتي» (24.4 · 23 — القسم 4).
     * سؤال واحد للشاشة: «إيه البنود اللي عليّ كمساهم، وإيه المستحقّ دلوقتي؟»
     */
    $penalty = rep_rule('task.contribution_no_delivery');
    $ownerReviewHours = $service->ownerReviewHours();
    $checkpointHours = $service->checkpointResponseHours();
@endphp

@section('content')
    <x-page-header
        title="مساهماتي"
        subtitle="البنود اللي اتدعيت ليها، وكلّ نقطة تفتيش مستحقّة عليك."
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/volunteer')], ['label' => 'مساهماتي']]" />

    {{-- 4 كروت KPI كحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-kpi label="دعوات جديدة" :value="$counters['invited']" icon="envelope" :state="$counters['invited'] > 0 ? 'warn' : 'idle'" />
        <x-kpi label="مفتوحة" :value="$counters['open']" icon="settings" state="ok" />
        <x-kpi label="بانتظار اعتماد المالك" :value="$counters['awaiting']" icon="hourglass" state="warn" />
        <x-kpi label="مكتملة" :value="$counters['done']" icon="check" state="honor" />
    </div>

    @if ($dueSoonCheckpoint)
        <div class="card p-3 mb-4 flex items-center gap-2 text-sm"
             style="border-inline-start: 3px solid var(--color-state-warn)">
            <span aria-hidden="true">▲</span>
            <span>تفتيش مستحقّ خلال ساعة — مهلة الردّ {{ $checkpointHours }} ساعة داخل {{ $activityWindow }}.</span>
        </div>
    @endif

    {{-- ثلاثة فلاتر ظاهرة فقط (2.15-أ-4) --}}
    <x-filters :action="route('volunteer.contributions')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الحالة</span>
            <select name="status" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">المالك</span>
            <select name="owner" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($ownerOptions as $owner)
                    <option value="{{ $owner->id }}" @selected($filters['owner'] === $owner->id)>{{ $owner->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="اسم البند…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($rows->isEmpty())
        <x-empty message="مفيش مساهمات عليك دلوقتي — أوّل دعوة هتوصلك هنا" />
    @else
        {{-- الجداول كروت رأسيّة على الموبايل بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="space-y-3">
            @foreach ($rows as $row)
                @php
                    $task = $tasks[$row->task_id] ?? null;
                    $owner = $owners[$row->invited_by] ?? null;
                    $points = $checkpoints[$row->id] ?? collect();
                    $state = $engine->windowState($row->internal_deadline_at);
                @endphp

                <article class="card p-4 animate-fadeup">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-bold truncate">{{ $row->item_title }}</h2>
                            {{-- اسم المهمّة الأمّ وديدلاينها الفعليّ — شرط دستوريّ في كلّ صفّ --}}
                            <p class="text-xs mt-1" style="color: var(--text-muted)">
                                المهمّة الأمّ: {{ $task?->title ?? '—' }}
                                @if ($task?->deadline_at)
                                    · ديدلاينها {{ $task->deadline_at->format('Y-m-d H:i') }}
                                @endif
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-muted)">
                                المالك: {{ $owner?->name ?? '—' }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <x-state-badge :state="$state"
                                           :label="'الديدلاين الداخليّ: '.$row->internal_deadline_at?->format('Y-m-d H:i')" />
                            <span class="text-sm font-bold">{{ (float) $row->vxp_value }} VXP</span>
                        </div>
                    </div>

                    {{-- شريط نقطتَي التفتيش: كلّ نقطة بحالتها ومهلة ردّها --}}
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @forelse ($points as $point)
                            @php
                                $pointState = match ($point->status) {
                                    'answered' => 'ok',
                                    'missed' => 'danger',
                                    default => $engine->windowState($point->response_due_at),
                                };
                            @endphp
                            <button type="button" data-modal-open="checkpoint-{{ $point->id }}"
                                    class="rounded-full px-3 py-1 text-xs motion-standard"
                                    style="background: color-mix(in srgb, var(--color-state-{{ state_color($pointState)['color'] }}) 15%, transparent);
                                           color: var(--color-state-{{ state_color($pointState)['color'] }})">
                                {{ state_color($pointState)['icon'] }}
                                تفتيش {{ $point->sequence }} — {{ $point->scheduled_at?->format('m/d H:i') }}
                            </button>
                        @empty
                            <span class="text-xs" style="color: var(--text-muted)">بلا نقاط تفتيش على البند ده</span>
                        @endforelse

                        <span class="text-xs" style="color: var(--text-muted)">
                            مهلة الردّ {{ $checkpointHours }} ساعة داخل {{ $activityWindow }}
                        </span>
                    </div>

                    {{-- شكل المخرجات مطويّ — سطر واحد يفتح على التفاصيل (2.15) --}}
                    <details class="mt-3">
                        <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">شكل المخرجات</summary>
                        <p class="text-sm mt-2 whitespace-pre-line">{{ $row->deliverable_spec ?: 'لم يُحدَّد.' }}</p>
                    </details>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-state-badge :state="$row->status === 'approved' ? 'ok' : ($row->status === 'expired' ? 'danger' : 'warn')"
                                       :label="$statuses[$row->status] ?? $row->status" />

                        @if ($row->status === 'invited')
                            <button type="button" data-modal-open="invite-{{ $row->id }}"
                                    class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c">افتح الدعوة</button>
                        @elseif (in_array($row->status, ['accepted', 'returned'], true))
                            <button type="button" data-modal-open="deliver-{{ $row->id }}"
                                    class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c">تسليم نهائيّ</button>
                        @elseif ($row->status === 'delivered')
                            <span class="text-xs" style="color: var(--text-muted)">
                                مهلة المالك {{ $ownerReviewHours }} ساعة، وبعدها اعتماد تلقائيّ بنقاطك كاملة.
                            </span>
                        @elseif ($row->auto_approved)
                            <span class="text-xs" style="color: var(--text-muted)">اعتُمد تلقائيًّا بنقاطك كاملة ✓</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection

@section('mobile_action')
    @if ($counters['invited'] > 0)
        <a href="{{ route('volunteer.contributions', ['status' => 'invited']) }}"
           class="btn block text-center rounded-xl px-4 py-3 text-sm font-semibold"
           style="background: var(--color-brand-500); color: #04201c">
            ردّ على الدعوات ({{ $counters['invited'] }})
        </a>
    @endif
@endsection

@push('modals')
    @foreach ($rows as $row)
        @php $task = $tasks[$row->task_id] ?? null; $owner = $owners[$row->invited_by] ?? null; @endphp

        {{-- بوب-أب الدعوة: يبدأ بسطر صارم قبل أيّ تفصيل (23 — القسم 4) --}}
        @if ($row->status === 'invited')
            <x-modal :id="'invite-'.$row->id" title="دعوة مساهمة">
                <p class="rounded-xl px-3 py-3 text-sm font-bold mb-4"
                   style="background: color-mix(in srgb, var(--color-state-danger) 12%, transparent); color: var(--color-state-danger)">
                    ◉ عدم التسليم = {{ $penalty }} على درجة الالتزام، وعدم الردّ على تفتيش في مهلته مثله.
                </p>

                <dl class="space-y-2 text-sm">
                    <div><dt class="text-xs" style="color: var(--text-muted)">البند</dt><dd>{{ $row->item_title }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">الديدلاين الداخليّ</dt>
                        <dd>{{ $row->internal_deadline_at?->format('Y-m-d H:i') }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">نقاط التفتيش</dt>
                        <dd>
                            @forelse ($checkpoints[$row->id] ?? [] as $point)
                                تفتيش {{ $point->sequence }}: {{ $point->scheduled_at?->format('Y-m-d H:i') }}
                                (الردّ خلال {{ $checkpointHours }} ساعة داخل {{ $activityWindow }})<br>
                            @empty
                                بلا نقاط تفتيش.
                            @endforelse
                        </dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">المكافأة</dt><dd>{{ (float) $row->vxp_value }} VXP</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">المالك</dt><dd>{{ $owner?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">المهمّة الأمّ وديدلاينها</dt>
                        <dd>{{ $task?->title ?? '—' }} — {{ $task?->deadline_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">شكل المخرجات</dt>
                        <dd class="whitespace-pre-line">{{ $row->deliverable_spec ?: 'لم يُحدَّد.' }}</dd></div>
                </dl>

                <x-slot:footer>
                    <div class="flex items-center gap-2">
                        <form method="post" action="{{ route('volunteer.contributions.respond', $row) }}">
                            @csrf
                            <input type="hidden" name="decision" value="accept">
                            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                    style="background: var(--color-brand-500); color: #04201c">قبول</button>
                        </form>
                        <form method="post" action="{{ route('volunteer.contributions.respond', $row) }}">
                            @csrf
                            <input type="hidden" name="decision" value="reject">
                            <button type="submit" class="rounded-xl px-4 py-2 text-sm"
                                    style="border: 1px solid var(--border); color: var(--text)">رفض</button>
                        </form>
                    </div>
                </x-slot:footer>
            </x-modal>
        @endif

        @if (in_array($row->status, ['accepted', 'returned'], true))
            <x-modal :id="'deliver-'.$row->id" title="تسليم نهائيّ">
                <form method="post" action="{{ route('volunteer.contributions.deliver', $row) }}" class="space-y-3">
                    @csrf
                    <x-form.input name="link" label="رابط المخرج" type="url" hint="أو اكتب المخرج نصًّا تحت." />
                    <label class="block">
                        <span class="block text-sm mb-1">المخرج نصًّا</span>
                        <textarea name="body" rows="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                    <x-form.input name="note" label="ملاحظة للمالك" />
                    <p class="text-xs" style="color: var(--text-muted)">
                        بعد التسليم: مهلة المالك {{ $ownerReviewHours }} ساعة، وبعدها اعتماد تلقائيّ بنقاطك كاملة.
                    </p>
                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">تسليم</button>
                </form>
            </x-modal>
        @endif

        @foreach ($checkpoints[$row->id] ?? [] as $point)
            <x-modal :id="'checkpoint-'.$point->id" :title="'نقطة تفتيش '.$point->sequence">
                <p class="text-sm mb-3">
                    الموعد: {{ $point->scheduled_at?->format('Y-m-d H:i') }} ·
                    مهلة الردّ حتى {{ $point->response_due_at?->format('Y-m-d H:i') }}
                    <span style="color: var(--text-muted)">({{ $activityWindow }} — وما خارجها لا يُحتسَب تأخيرًا)</span>
                </p>

                @if ($point->response_body)
                    <p class="text-sm rounded-xl p-3 mb-3" style="background: var(--surface-sunken)">{{ $point->response_body }}</p>
                @endif

                <form method="post" action="{{ route('volunteer.contributions.checkpoint', $point) }}" class="space-y-3">
                    @csrf
                    <label class="block">
                        <span class="block text-sm mb-1">كلّ اللي وصلت له</span>
                        <textarea name="body" rows="4" required class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">إرسال الردّ</button>
                </form>
            </x-modal>
        @endforeach
    @endforeach
@endpush
