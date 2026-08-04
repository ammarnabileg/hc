@extends('layouts.volunteer')

@section('title', setting('volunteer.contributions.title', 'مساهماتي'))

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
        :title="setting('volunteer.contributions.title', 'مساهماتي')"
        :subtitle="setting('volunteer.contributions.subtitle', 'البنود اللي اتدعيت ليها، وكلّ نقطة تفتيش مستحقّة عليك.')"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer')], ['label' => setting('volunteer.contributions.title', 'مساهماتي')]]" />

    {{-- 4 كروت KPI كحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-kpi :label="setting('volunteer.contributions.label', 'دعوات جديدة')" :value="$counters['invited']" icon="envelope" :state="$counters['invited'] > 0 ? 'warn' : 'idle'" />
        <x-kpi :label="setting('volunteer.contributions.label_2', 'مفتوحة')" :value="$counters['open']" icon="settings" state="ok" />
        <x-kpi :label="setting('volunteer.contributions.label_3', 'بانتظار اعتماد المالك')" :value="$counters['awaiting']" icon="hourglass" state="warn" />
        <x-kpi :label="setting('volunteer.contributions.label_4', 'مكتملة')" :value="$counters['done']" icon="check" state="honor" />
    </div>

    @if ($dueSoonCheckpoint)
        <div class="card p-3 mb-4 flex items-center gap-2 text-sm"
             style="border-inline-start: 3px solid var(--color-state-warn)">
            <span aria-hidden="true">▲</span>
            <span>{{ setting('volunteer.contributions.text', 'تفتيش مستحقّ خلال ساعة — مهلة الردّ') }} {{ $checkpointHours }} {{ setting('volunteer.contributions.text_2', 'ساعة داخل') }} {{ $activityWindow }}.</span>
        </div>
    @endif

    {{-- ثلاثة فلاتر ظاهرة فقط (2.15-أ-4) --}}
    <x-filters :action="route('volunteer.contributions')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.status', 'الحالة') }}</span>
            <select name="status" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.contributions.field', 'المالك') }}</span>
            <select name="owner" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                @foreach ($ownerOptions as $owner)
                    <option value="{{ $owner->id }}" @selected($filters['owner'] === $owner->id)>{{ $owner->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.common.search', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('volunteer.contributions.placeholder', 'اسم البند…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($rows->isEmpty())
        <x-empty :message="setting('volunteer.contributions.empty', 'مفيش مساهمات عليك دلوقتي — أوّل دعوة هتوصلك هنا')" />
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
                                {{ setting('volunteer.contributions.field_2', 'المهمّة الأمّ:') }} {{ $task?->title ?? '—' }}
                                @if ($task?->deadline_at)
                                    · {{ setting('volunteer.contributions.field_3', 'ديدلاينها') }} {{ $task->deadline_at->format('Y-m-d H:i') }}
                                @endif
                            </p>
                            <p class="text-xs mt-1" style="color: var(--text-muted)">
                                {{ setting('volunteer.contributions.field_4', 'المالك:') }} {{ $owner?->name ?? '—' }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <x-state-badge :state="$state"
                                           :label="setting('volunteer.contributions.label_5', 'الديدلاين الداخليّ: ').$row->internal_deadline_at?->format('Y-m-d H:i')" />
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
                                {{ setting('volunteer.contributions.action', 'تفتيش') }} {{ $point->sequence }} — {{ $point->scheduled_at?->format('m/d H:i') }}
                            </button>
                        @empty
                            <span class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.contributions.field_5', 'بلا نقاط تفتيش على البند ده') }}</span>
                        @endforelse

                        <span class="text-xs" style="color: var(--text-muted)">
                            {{ setting('volunteer.contributions.field_6', 'مهلة الردّ') }} {{ $checkpointHours }} {{ setting('volunteer.contributions.text_2', 'ساعة داخل') }} {{ $activityWindow }}
                        </span>
                    </div>

                    {{-- شكل المخرجات مطويّ — سطر واحد يفتح على التفاصيل (2.15) --}}
                    <details class="mt-3">
                        <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">{{ setting('volunteer.common.output_format', 'شكل المخرجات') }}</summary>
                        <p class="text-sm mt-2 whitespace-pre-line">{{ $row->deliverable_spec ?: setting('volunteer.contributions.text_3', 'لم يُحدَّد.') }}</p>
                    </details>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-state-badge :state="$row->status === 'approved' ? 'ok' : ($row->status === 'expired' ? 'danger' : 'warn')"
                                       :label="$statuses[$row->status] ?? $row->status" />

                        @if ($row->status === 'invited')
                            <button type="button" data-modal-open="invite-{{ $row->id }}"
                                    class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.contributions.action_2', 'افتح الدعوة') }}</button>
                        @elseif (in_array($row->status, ['accepted', 'returned'], true))
                            <button type="button" data-modal-open="deliver-{{ $row->id }}"
                                    class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.contributions.action_3', 'تسليم نهائيّ') }}</button>
                        @elseif ($row->status === 'delivered')
                            <span class="text-xs" style="color: var(--text-muted)">
                                {{ setting('volunteer.contributions.field_7', 'مهلة المالك') }} {{ $ownerReviewHours }} {{ setting('volunteer.contributions.field_8', 'ساعة، وبعدها اعتماد تلقائيّ بنقاطك كاملة.') }}
                            </span>
                        @elseif ($row->auto_approved)
                            <span class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.contributions.field_9', 'اعتُمد تلقائيًّا بنقاطك كاملة ✓') }}</span>
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
            {{ str_replace(':count', $counters['invited'], (string) setting('volunteer.contributions.link', 'ردّ على الدعوات (:count)')) }}
        </a>
    @endif
@endsection

@push('modals')
    @foreach ($rows as $row)
        @php $task = $tasks[$row->task_id] ?? null; $owner = $owners[$row->invited_by] ?? null; @endphp

        {{-- بوب-أب الدعوة: يبدأ بسطر صارم قبل أيّ تفصيل (23 — القسم 4) --}}
        @if ($row->status === 'invited')
            <x-modal :id="'invite-'.$row->id" :title="setting('volunteer.contributions.tooltip', 'دعوة مساهمة')">
                <p class="rounded-xl px-3 py-3 text-sm font-bold mb-4"
                   style="background: color-mix(in srgb, var(--color-state-danger) 12%, transparent); color: var(--color-state-danger)">
                    ◉ {{ setting('volunteer.contributions.field_10', 'عدم التسليم =') }} {{ $penalty }} {{ setting('volunteer.contributions.field_11', 'على درجة الالتزام، وعدم الردّ على تفتيش في مهلته مثله.') }}
                </p>

                <dl class="space-y-2 text-sm">
                    <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.common.item', 'البند') }}</dt><dd>{{ $row->item_title }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.contributions.field_12', 'الديدلاين الداخليّ') }}</dt>
                        <dd>{{ $row->internal_deadline_at?->format('Y-m-d H:i') }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.contributions.field_13', 'نقاط التفتيش') }}</dt>
                        <dd>
                            @forelse ($checkpoints[$row->id] ?? [] as $point)
                                {{ setting('volunteer.contributions.action', 'تفتيش') }} {{ $point->sequence }}: {{ $point->scheduled_at?->format('Y-m-d H:i') }}
                                ({{ setting('volunteer.contributions.field_14', 'الردّ خلال') }} {{ $checkpointHours }} {{ setting('volunteer.contributions.text_2', 'ساعة داخل') }} {{ $activityWindow }})<br>
                            @empty
                                {{ setting('volunteer.contributions.field_15', 'بلا نقاط تفتيش.') }}
                            @endforelse
                        </dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.contributions.field_16', 'المكافأة') }}</dt><dd>{{ (float) $row->vxp_value }} VXP</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.contributions.field', 'المالك') }}</dt><dd>{{ $owner?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.contributions.field_17', 'المهمّة الأمّ وديدلاينها') }}</dt>
                        <dd>{{ $task?->title ?? '—' }} — {{ $task?->deadline_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.common.output_format', 'شكل المخرجات') }}</dt>
                        <dd class="whitespace-pre-line">{{ $row->deliverable_spec ?: setting('volunteer.contributions.text_3', 'لم يُحدَّد.') }}</dd></div>
                </dl>

                <x-slot:footer>
                    <div class="flex items-center gap-2">
                        <form method="post" action="{{ route('volunteer.contributions.respond', $row) }}">
                            @csrf
                            <input type="hidden" name="decision" value="accept">
                            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.contributions.action_4', 'قبول') }}</button>
                        </form>
                        <form method="post" action="{{ route('volunteer.contributions.respond', $row) }}">
                            @csrf
                            <input type="hidden" name="decision" value="reject">
                            <button type="submit" class="rounded-xl px-4 py-2 text-sm"
                                    style="border: 1px solid var(--border); color: var(--text)">{{ setting('volunteer.contributions.action_5', 'رفض') }}</button>
                        </form>
                    </div>
                </x-slot:footer>
            </x-modal>
        @endif

        @if (in_array($row->status, ['accepted', 'returned'], true))
            <x-modal :id="'deliver-'.$row->id" :title="setting('volunteer.contributions.action_3', 'تسليم نهائيّ')">
                <form method="post" action="{{ route('volunteer.contributions.deliver', $row) }}" class="space-y-3">
                    @csrf
                    <x-form.input name="link" :label="setting('volunteer.contributions.label_6', 'رابط المخرج')" type="url" :hint="setting('volunteer.contributions.hint', 'أو اكتب المخرج نصًّا تحت.')" />
                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('volunteer.contributions.field_18', 'المخرج نصًّا') }}</span>
                        <textarea name="body" rows="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                    <x-form.input name="note" :label="setting('volunteer.contributions.label_7', 'ملاحظة للمالك')" />
                    <p class="text-xs" style="color: var(--text-muted)">
                        {{ setting('volunteer.contributions.field_19', 'بعد التسليم: مهلة المالك') }} {{ $ownerReviewHours }} {{ setting('volunteer.contributions.field_8', 'ساعة، وبعدها اعتماد تلقائيّ بنقاطك كاملة.') }}
                    </p>
                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.contributions.action_6', 'تسليم') }}</button>
                </form>
            </x-modal>
        @endif

        @foreach ($checkpoints[$row->id] ?? [] as $point)
            <x-modal :id="'checkpoint-'.$point->id" :title="setting('volunteer.contributions.tooltip_2', 'نقطة تفتيش ').$point->sequence">
                <p class="text-sm mb-3">
                    {{ setting('volunteer.contributions.field_20', 'الموعد:') }} {{ $point->scheduled_at?->format('Y-m-d H:i') }} ·
                    {{ setting('volunteer.contributions.field_21', 'مهلة الردّ حتى') }} {{ $point->response_due_at?->format('Y-m-d H:i') }}
                    <span style="color: var(--text-muted)">{{ str_replace(':window', $activityWindow, (string) setting('volunteer.contributions.activity_window_note', '(:window — وما خارجها لا يُحتسَب تأخيرًا)')) }}</span>
                </p>

                @if ($point->response_body)
                    <p class="text-sm rounded-xl p-3 mb-3" style="background: var(--surface-sunken)">{{ $point->response_body }}</p>
                @endif

                <form method="post" action="{{ route('volunteer.contributions.checkpoint', $point) }}" class="space-y-3">
                    @csrf
                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('volunteer.contributions.field_23', 'كلّ اللي وصلت له') }}</span>
                        <textarea name="body" rows="4" required class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.contributions.action_7', 'إرسال الردّ') }}</button>
                </form>
            </x-modal>
        @endforeach
    @endforeach
@endpush
