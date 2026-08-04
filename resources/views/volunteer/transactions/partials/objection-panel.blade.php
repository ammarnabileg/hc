@php
    /** بانل الاعتراض: المعاملة الأصليّة · السبب · سلسلة النقاش · سلّم التصعيد · النتيجة */
    $transaction = $objection->transaction;
    $value = (float) ($transaction?->applied_amount ?? $transaction?->amount ?? 0);
    $sign = $value > 0 ? '+' : ($value < 0 ? '−' : '');
    $symbol = $value > 0 ? '●' : ($value < 0 ? '◉' : '○');
    $color = $value > 0 ? 'ok' : ($value < 0 ? 'danger' : 'idle');
    $active = $service->isActive($objection);
    $preview = $service->correctionPreview($objection);
@endphp

<div class="card p-4 space-y-4">
    <div class="flex items-center justify-between gap-2">
        <h2 class="font-bold flex items-center gap-2">
            @include('volunteer.meetings.partials.icon', ['name' => 'objection'])
            {{ setting('volunteer.transactions_objection_panel.heading', 'اعتراض #') }}{{ $objection->id }}
        </h2>
        <div class="flex items-center gap-2">
            <x-state-badge :state="$service->statusState($objection->status)" :label="$service->statusLabel($objection->status)" />
            <button type="button" data-sheet-close class="text-sm opacity-70" aria-label="{{ setting('volunteer.transactions_objection_panel.aria', 'إغلاق') }}">✕</button>
        </div>
    </div>

    {{-- المعاملة الأصليّة — لا تُعدَّل أبدًا، والتصحيح بمعاملة عكسيّة (13.4-ط) --}}
    <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
        <div class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.transactions_objection_panel.text', 'المعاملة الأصليّة #') }}{{ $objection->transaction_id }}</div>
        <div class="mt-1 flex items-center justify-between gap-2">
            <span class="text-sm">{{ $transaction?->reason }}</span>
            <strong style="color: var(--color-state-{{ $color }})">
                <span aria-hidden="true">{{ $symbol }}</span> {{ $sign }}{{ abs($value) }}
            </strong>
        </div>
        <div class="mt-1 text-xs" style="color: var(--text-muted)">
            {{ $transaction?->created_at?->format('Y-m-d H:i') }} · {{ $transaction?->currency?->name_ar }}
        </div>
    </div>

    <div>
        <h3 class="text-sm font-bold mb-1">{{ setting('volunteer.transactions_objection_panel.heading_2', 'سبب اعتراضي') }}</h3>
        <p class="text-sm whitespace-pre-line">{{ $objection->reason }}</p>
        @if ($objection->attachment_path)
            <a href="{{ \Illuminate\Support\Facades\Storage::url($objection->attachment_path) }}" target="_blank" rel="noopener"
               class="mt-1 inline-flex items-center gap-1 text-xs" style="color: var(--color-brand-400)">
                @include('volunteer.meetings.partials.icon', ['name' => 'attachment']) {{ setting('volunteer.transactions_objection_panel.link', 'المرفق') }}
            </a>
        @endif
    </div>

    {{-- سلّم التصعيد المرئيّ: علامة على المستوى الحاليّ وعدّاد نافذته (13.4-ط) --}}
    <div>
        <h3 class="text-sm font-bold mb-2 flex items-center gap-2">
            @include('volunteer.meetings.partials.icon', ['name' => 'escalation']) {{ setting('volunteer.transactions_objection_panel.heading_3', 'سلّم التصعيد') }}
        </h3>
        @if ($ladder->isEmpty())
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.transactions_objection_panel.text_2', 'مفيش أبلاين مسجَّل — الاعتراض عند السقف مباشرةً.') }}</p>
        @else
            <ol class="space-y-2">
                @foreach ($ladder as $step)
                    <li class="flex items-center gap-2 text-sm rounded-xl px-3 py-2"
                        style="border: 1px solid {{ $step['is_current'] ? 'var(--color-brand-600)' : 'var(--border)' }}">
                        <span class="text-xs w-5 text-center" style="color: var(--text-muted)">{{ $step['level'] }}</span>
                        <x-avatar :user="$step['user']" size="7" />
                        <span class="flex-1 truncate">{{ $step['user']->name }}</span>
                        @if ($step['is_current'])
                            <x-state-badge :state="$service->isOverdue($objection) ? 'danger' : 'warn'" :label="setting('volunteer.transactions_objection_panel.label', 'المستوى الحاليّ')" />
                            @if ($objection->sla_due_at)
                                <span class="text-xs" data-countdown="{{ $objection->sla_due_at->toIso8601String() }}"
                                      data-prefix="{{ setting('volunteer.transactions_objection_panel.prefix', 'باقي') }}">{{ $objection->sla_due_at->diffForHumans() }}</span>
                            @endif
                        @elseif ($step['is_passed'])
                            <x-state-badge state="idle" :label="setting('volunteer.transactions_objection_panel.label_2', 'عدّى')" />
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    {{-- سلسلة النقاش --}}
    <div>
        <h3 class="text-sm font-bold mb-2">{{ setting('volunteer.transactions_objection_panel.heading_4', 'سلسلة النقاش') }}</h3>
        @if ($objection->messages->isEmpty())
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.transactions_objection_panel.text_3', 'مفيش رسائل لسّه.') }}</p>
        @else
            <ul class="space-y-2">
                @foreach ($objection->messages as $message)
                    <li class="rounded-xl p-3 text-sm" style="background: var(--surface-sunken)">
                        <div class="text-xs mb-1" style="color: var(--text-muted)">
                            {{ $message->user?->name }} · {{ $message->created_at?->diffForHumans() }}
                        </div>
                        <div class="whitespace-pre-line">{{ $message->body }}</div>
                        @if ($message->attachment_path)
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($message->attachment_path) }}"
                               target="_blank" rel="noopener" class="text-xs" style="color: var(--color-brand-400)">{{ setting('volunteer.common.attachment', 'مرفق') }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- النتيجة والمعاملة التصحيحيّة --}}
    @if (! $active)
        <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
            <h3 class="text-sm font-bold mb-1">{{ setting('volunteer.common.result', 'النتيجة') }}</h3>
            <p class="text-sm">{{ $objection->decision_note ?: setting('volunteer.transactions_objection_panel.text_4', 'اتقفل الاعتراض.') }}</p>

            @if ($objection->correction_transaction)
                <p class="text-xs mt-2" style="color: var(--text-muted)">
                    {{ setting('volunteer.transactions_objection_panel.text_5', 'معاملة تصحيحيّة #') }}{{ $objection->correction_transaction->id }} —
                    {{ setting('volunteer.transactions_objection_panel.text_6', 'الأصل ما اتعدّلش، والتصحيح بمعاملة عكسيّة موثّقة.') }}
                </p>
                <x-state-badge state="ok" :label="setting('volunteer.transactions_objection_panel.label_3', 'مصحِّحة')" />
            @endif
        </div>
    @else
        {{-- «إضافة تفاصيل» متاحة أيّ وقت ما دام الاعتراض ساريًا --}}
        <form method="post" action="{{ route('volunteer.objections.messages', $objection) }}"
              enctype="multipart/form-data" class="space-y-2">
            @csrf
            <label class="block text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.transactions_objection_panel.field', 'إضافة تفاصيل') }}</span>
                <textarea name="body" rows="3" required minlength="2" maxlength="2000"
                          class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>
            <input type="file" name="attachment" class="w-full text-xs">
            <p class="text-xs" style="color: var(--text-muted)">
                {{ setting('volunteer.transactions_objection_panel.text_7', 'لو اتقبل الاعتراض، درجتك هتتحرّك من') }} {{ $preview['from'] }} {{ setting('volunteer.transactions_objection_panel.text_8', 'لـ') }}{{ $preview['to'] }} {{ setting('volunteer.transactions_objection_panel.text_9', 'بمعاملة عكسيّة.') }}
            </p>
            <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.transactions_objection_panel.field', 'إضافة تفاصيل') }}</button>
        </form>
    @endif
</div>

@include('volunteer.meetings.partials.countdown')
