@php
    /**
     * بانل مكتب الاعتراضات (24.4-8): المعاملة الأصليّة وقيمتها · سبب الاعتراض ·
     * سلسلة النقاش · سلّم التصعيد المرئيّ · **Audit**.
     *
     * وأفعال البتّ: **ردّ** فعلًا رئيسيًّا واحدًا، والباقي (تصعيد · تعديل/عكس ·
     * رفض) في «⋯» (2.15-أ). وما لا يملكه المستخدم **يُخفى لا يُعطَّل** (2.15-أ-7).
     */
    $transaction = $objection->transaction;
    $value = (float) ($transaction?->applied_amount ?? $transaction?->amount ?? 0);
    $sign = $value > 0 ? '+' : ($value < 0 ? '−' : '');
    $symbol = $value > 0 ? '●' : ($value < 0 ? '◉' : '○');
    $color = $value > 0 ? 'ok' : ($value < 0 ? 'danger' : 'idle');
    $mayDecide = $desk->mayDecide(auth()->user(), $objection);
    $preview = $service->correctionPreview($objection);
@endphp

<div class="card p-4 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-bold flex items-center gap-2 min-w-0">
            @include('volunteer.meetings.partials.icon', ['name' => 'objection'])
            <span class="truncate">{{ setting('volunteer.escalations_objection_desk_panel.heading', 'اعتراض #') }}{{ $objection->id }} — {{ $objection->user?->name }}</span>
        </h2>
        <div class="flex items-center gap-2 shrink-0">
            <x-state-badge :state="$service->statusState($objection->status)" :label="$service->statusLabel($objection->status)" />
            @if ($service->isOverdue($objection))
                <x-state-badge state="danger" :label="setting('volunteer.escalations_objection_desk_panel.label', 'متأخّر عن الـSLA')" />
            @endif
            <button type="button" data-sheet-close class="text-sm opacity-70" aria-label="{{ setting('volunteer.escalations_objection_desk_panel.aria', 'إغلاق') }}">✕</button>
        </div>
    </div>

    {{-- المعاملة الأصليّة وقيمتها — لا تُعدَّل أبدًا (13.4-ط) --}}
    <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
        <div class="text-xs flex items-center gap-1" style="color: var(--text-muted)">
            @include('volunteer.meetings.partials.icon', ['name' => 'transaction'])
            {{ setting('volunteer.escalations_objection_desk_panel.text', 'المعاملة الأصليّة #') }}{{ $objection->transaction_id }}
            @if ($entity)
                · @include('volunteer.meetings.partials.icon', ['name' => 'entity']) {{ $entity->name_ar }}
            @endif
        </div>
        <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
            <span class="text-sm min-w-0 break-words">{{ $transaction?->reason }}</span>
            <strong class="shrink-0" style="color: var(--color-state-{{ $color }})">
                <span aria-hidden="true">{{ $symbol }}</span> {{ $sign }}{{ abs($value) }}
            </strong>
        </div>
        <div class="mt-1 text-xs" style="color: var(--text-muted)">
            {{ $transaction?->created_at?->format('Y-m-d H:i') }} · {{ $transaction?->currency?->name_ar }}
        </div>
    </div>

    <div>
        <h3 class="text-sm font-bold mb-1">{{ setting('volunteer.escalations_objection_desk_panel.heading_2', 'سبب الاعتراض') }}</h3>
        <p class="text-sm whitespace-pre-line break-words">{{ $objection->reason }}</p>
        @if ($objection->attachment_path)
            <a href="{{ \Illuminate\Support\Facades\Storage::url($objection->attachment_path) }}" target="_blank"
               rel="noopener" class="mt-1 inline-flex items-center gap-1 text-xs" style="color: var(--color-brand-400)">
                @include('volunteer.meetings.partials.icon', ['name' => 'attachment']) {{ setting('volunteer.escalations_objection_desk_panel.link', 'المرفق') }}
            </a>
        @endif
    </div>

    {{-- سلّم التصعيد المرئيّ — يراه الجميع حتى المتطوّع (13.4-ط) --}}
    <div>
        <h3 class="text-sm font-bold mb-2 flex items-center gap-2">
            @include('volunteer.meetings.partials.icon', ['name' => 'escalation']) {{ setting('volunteer.escalations_objection_desk_panel.heading_3', 'سلّم التصعيد') }}
        </h3>
        @if ($ladder->isEmpty())
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.escalations_objection_desk_panel.text_2', 'مفيش أبلاين مسجَّل — الاعتراض عند السقف مباشرةً.') }}</p>
        @else
            <ol class="space-y-2">
                @foreach ($ladder as $step)
                    <li class="flex flex-wrap items-center gap-2 text-sm rounded-xl px-3 py-2"
                        style="border: 1px solid {{ $step['is_current'] ? 'var(--color-brand-600)' : 'var(--border)' }}">
                        <span class="text-xs w-5 text-center" style="color: var(--text-muted)">{{ $step['level'] }}</span>
                        <x-avatar :user="$step['user']" size="7" />
                        <span class="flex-1 truncate">{{ $step['user']->name }}</span>
                        @if ($step['is_current'])
                            <x-state-badge :state="$service->isOverdue($objection) ? 'danger' : 'warn'" :label="setting('volunteer.escalations_objection_desk_panel.label_2', 'المستوى الحاليّ')" />
                            @if ($objection->sla_due_at)
                                <span class="text-xs" data-countdown="{{ $objection->sla_due_at->toIso8601String() }}"
                                      data-prefix="{{ setting('volunteer.escalations_objection_desk_panel.prefix', 'باقي') }}">{{ $objection->sla_due_at->diffForHumans() }}</span>
                            @endif
                        @elseif ($step['is_passed'])
                            <x-state-badge state="idle" :label="setting('volunteer.escalations_objection_desk_panel.label_3', 'عدّى')" />
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    {{-- سلسلة النقاش --}}
    <div>
        <h3 class="text-sm font-bold mb-2">{{ setting('volunteer.escalations_objection_desk_panel.heading_4', 'سلسلة النقاش') }}</h3>
        @if ($objection->messages->isEmpty())
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.escalations_objection_desk_panel.text_3', 'مفيش رسائل لسّه.') }}</p>
        @else
            <ul class="space-y-2">
                @foreach ($objection->messages as $message)
                    <li class="rounded-xl p-3 text-sm" style="background: var(--surface-sunken)">
                        <div class="text-xs mb-1" style="color: var(--text-muted)">
                            {{ $message->user?->name }} · {{ $message->created_at?->diffForHumans() }}
                        </div>
                        <div class="whitespace-pre-line break-words">{{ $message->body }}</div>
                        @if ($message->attachment_path)
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($message->attachment_path) }}"
                               target="_blank" rel="noopener" class="text-xs" style="color: var(--color-brand-400)">{{ setting('volunteer.common.attachment', 'مرفق') }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Audit: مَن فعل ماذا ومتى — بلا اسمٍ يبقى القرار مجهول الصاحب --}}
    <details class="rounded-xl px-3 py-2" style="border: 1px solid var(--border)">
        <summary class="text-sm font-bold cursor-pointer select-none">Audit</summary>
        <ol class="mt-2 space-y-1 text-xs" style="color: var(--text-muted)">
            <li>{{ setting('volunteer.escalations_objection_desk_panel.bullet', 'رُفِع الاعتراض ·') }} {{ $objection->user?->name }} · {{ $objection->created_at?->format('Y-m-d H:i') }}</li>
            @foreach ($objection->messages as $message)
                <li>{{ setting('volunteer.escalations_objection_desk_panel.bullet_2', 'رسالة ·') }} {{ $message->user?->name }} · {{ $message->created_at?->format('Y-m-d H:i') }}</li>
            @endforeach
            @if ($objection->sla_due_at)
                <li>{{ setting('volunteer.escalations_objection_desk_panel.bullet_3', 'مهلة الردّ الحاليّة ·') }} {{ $objection->sla_due_at->format('Y-m-d H:i') }}</li>
            @endif
            @if ($objection->closed_at)
                <li>
                    {{ setting('volunteer.escalations_objection_desk_panel.bullet_4', 'القرار «') }}{{ $service->statusLabel($objection->status) }}» ·
                    {{ $decider?->name ?? setting('volunteer.escalations_objection_desk_panel.text_4', 'غير مسجَّل') }} · {{ $objection->closed_at->format('Y-m-d H:i') }}
                </li>
            @endif
            @if ($objection->correction_transaction)
                <li>{{ setting('volunteer.escalations_objection_desk_panel.bullet_5', 'معاملة تصحيحيّة #') }}{{ $objection->correction_transaction->id }} — {{ setting('volunteer.escalations_objection_desk_panel.bullet_6', 'والأصل كما هو.') }}</li>
            @endif
        </ol>
    </details>

    {{-- النتيجة بعد الإغلاق: للقراءة فقط، والقرار لا يُعاد --}}
    @if (! $service->isActive($objection))
        <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
            <h3 class="text-sm font-bold mb-1">{{ setting('volunteer.common.result', 'النتيجة') }}</h3>
            <p class="text-sm whitespace-pre-line break-words">{{ $objection->decision_note ?: setting('volunteer.escalations_objection_desk_panel.text_5', 'اتقفل الاعتراض.') }}</p>
            @if ($objection->correction_transaction)
                <div class="mt-2 flex items-center gap-2">
                    <x-state-badge state="ok" :label="setting('volunteer.escalations_objection_desk_panel.label_4', 'مصحِّحة')" />
                    <span class="text-xs" style="color: var(--text-muted)">
                        {{ setting('volunteer.escalations_objection_desk_panel.bullet_5', 'معاملة تصحيحيّة #') }}{{ $objection->correction_transaction->id }} — {{ $notice }}
                    </span>
                </div>
            @endif
        </div>
    @elseif ($mayDecide)
        {{-- أفعال البتّ: فعل رئيسيّ واحد والباقي في «⋯» (2.15-أ) --}}
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" data-modal-open="obj-reply-{{ $objection->id }}"
                    class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c; min-block-size: 44px">{{ setting('volunteer.escalations_objection_desk_panel.action', 'ردّ') }}</button>

            {{-- «⋯» نفسه يُخفى لمن لا يملك أيّ فعلٍ فيه — لا يُفتَح على قائمةٍ فارغة --}}
            @canany(['objections.assign', 'objections.approve', 'objections.reject'])
                <details class="relative">
                    <summary class="btn rounded-xl px-4 py-2 text-sm cursor-pointer select-none list-none"
                             style="border: 1px solid var(--border); min-block-size: 44px" aria-label="{{ setting('volunteer.escalations_objection_desk_panel.aria_2', 'إجراءات أخرى') }}">⋯</summary>
                    <div class="card absolute z-20 mt-1 p-1 space-y-1 min-w-48" style="inset-inline-end: 0">
                        @can('objections.assign')
                            <button type="button" data-modal-open="obj-escalate-{{ $objection->id }}"
                                    class="w-full text-start rounded-xl px-3 py-2 text-sm motion-standard">{{ setting('volunteer.escalations_objection_desk_panel.action_2', 'تصعيد لمن فوقي') }}</button>
                        @endcan
                        @can('objections.approve')
                            <button type="button" data-modal-open="obj-accept-{{ $objection->id }}"
                                    class="w-full text-start rounded-xl px-3 py-2 text-sm motion-standard">{{ setting('volunteer.escalations_objection_desk_panel.action_3', 'تعديل/عكس (قبول)') }}</button>
                        @endcan
                        @can('objections.reject')
                            <button type="button" data-modal-open="obj-reject-{{ $objection->id }}"
                                    class="w-full text-start rounded-xl px-3 py-2 text-sm motion-standard">{{ setting('volunteer.escalations_objection_desk_panel.action_4', 'رفض بسبب مكتوب') }}</button>
                        @endcan
                    </div>
                </details>
            @endcanany

            <span class="text-xs" style="color: var(--text-muted)">
                {{ setting('volunteer.escalations_objection_desk_panel.text_6', 'لو قُبِل: Rep يرتفع من') }} {{ $preview['from'] }} {{ setting('volunteer.common.to', 'إلى') }} {{ $preview['to'] }}.
            </span>
        </div>
    @else
        <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.escalations_objection_desk_panel.text_7', 'الاعتراض ده مش على مكتبك — للقراءة فقط.') }}</p>
    @endif
</div>

@include('volunteer.meetings.partials.countdown')
