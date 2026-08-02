@extends('layouts.app')

@section('title', 'يحتاج قرارك')

@section('content')
    <x-page-header
        title="يحتاج قرارك"
        subtitle="كلّ حالة معلّقة على مكتبك بنافذتها قبل أن تصعد."
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/volunteer')], ['label' => 'يحتاج قرارك']]" />

    <p class="card p-3 mb-4 text-sm" style="border-inline-start: 3px solid var(--color-state-warn)">
        ▲ فوات نافذتك يرفع الحالة لأبلاينك وعليك أثر التباطؤ ({{ rep_rule('task.slowdown') }}).
    </p>

    <div class="grid grid-cols-3 gap-3 mb-4">
        <x-kpi label="داخل النافذة" :value="$counters['ok']" icon="●" state="ok" />
        <x-kpi label="اقتربت" :value="$counters['warn']" icon="▲" state="warn" />
        <x-kpi label="فاتت" :value="$counters['danger']" icon="◉" state="danger" />
    </div>

    <x-filters :action="route('volunteer.escalations')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">نوع الحالة</span>
            <select name="case_type" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($catalog as $type => $meta)
                    <option value="{{ $type }}" @selected($filters['case_type'] === $type)>{{ $meta['icon'] }} {{ $meta['label'] }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الشخص</span>
            <select name="person" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($requesters as $person)
                    <option value="{{ $person->id }}" @selected($filters['person'] === $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="urgent" value="1" @checked($filters['urgent'])
                   onchange="this.form.submit()" style="accent-color: var(--color-brand-500)">
            الأقرب لانتهاء النافذة
        </label>
    </x-filters>

    @if ($rows->isEmpty())
        <x-empty message="مكتبك فاضي 👌" />
    @else
        <div class="space-y-3">
            @foreach ($rows as $case)
                @php
                    $meta = $catalog[$case->case_type] ?? [];
                    $subject = $subjects[$case->id] ?? null;
                    $state = $engine->windowState($case->window_due_at);
                    $payload = $engine->payload($case);
                @endphp

                <article class="card p-4 animate-fadeup">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-bold">
                                <span aria-hidden="true">{{ $meta['icon'] ?? '•' }}</span>
                                {{ $meta['label'] ?? $case->case_type }}
                            </h2>
                            <p class="text-xs mt-1" style="color: var(--text-muted)">
                                {{ $requesters[$case->requested_by]->name ?? 'النظام' }}
                                @if ($subject?->title) · {{ $subject->title }} @endif
                                · المستوى {{ $case->level }}
                            </p>
                            @if (! empty($payload['note']))
                                <p class="text-sm mt-2">{{ $payload['note'] }}</p>
                            @endif
                        </div>

                        <div class="flex flex-col items-end gap-2 shrink-0">
                            <x-state-badge :state="$state"
                                           :label="($case->is_top_level ? 'نافذة السقف 48س' : 'نافذة 24س').': '.$case->window_due_at?->format('Y-m-d H:i')" />
                        </div>
                    </div>

                    {{-- التسوية الآليّة المنتظَرة مكتوبة صراحةً — الشفافيّة نفسها رادع --}}
                    <p class="text-xs mt-3" style="color: var(--text-muted)">
                        لو فاتت نافذة السقف: <strong>{{ $meta['settlement_label'] ?? '—' }}</strong>
                        @if (! empty($meta['notice'])) · {{ $meta['notice'] }} @endif
                    </p>

                    <div class="mt-3">
                        <button type="button" data-modal-open="decide-{{ $case->id }}"
                                class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                style="background: var(--color-brand-500); color: #04201c">اتّخِذ قرارك</button>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection

@push('modals')
    @foreach ($rows as $case)
        @php $meta = $catalog[$case->case_type] ?? []; @endphp

        <x-modal :id="'decide-'.$case->id" :title="($meta['label'] ?? $case->case_type).' — قرارك'">
            <form method="post" action="{{ route('volunteer.escalations.decide', $case) }}" class="space-y-3">
                @csrf

                @if (! empty($meta['notice']))
                    <p class="rounded-xl px-3 py-2 text-sm"
                       style="background: color-mix(in srgb, var(--color-state-ok) 12%, transparent)">
                        ● {{ $meta['notice'] }}
                    </p>
                @endif

                <fieldset class="space-y-2">
                    <legend class="text-sm mb-1">القرار</legend>
                    @foreach ($meta['decisions'] ?? [] as $key => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="decision" value="{{ $key }}" required
                                   style="accent-color: var(--color-brand-500)">
                            {{ $label }}
                        </label>
                    @endforeach
                </fieldset>

                @if ($case->case_type === 'repeated_return')
                    {{-- الحالة 8: قيمة Rep يدويّة محصورة بين الحدّين، بمبرّر إجباريّ --}}
                    <x-form.input name="rep_value" label="قيمة Rep اليدويّة" type="number" step="0.05"
                                  :min="$repMin" :max="$repMax"
                                  :hint="'محصورة بين '.$repMax.' و'.$repMin.' — وتُطبَّق مع «إنهاء» فقط.'" />
                    <label class="block">
                        <span class="block text-sm mb-1">المبرّر <span style="color: var(--color-state-danger)">*</span></span>
                        <textarea name="justification" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                @endif

                <label class="block">
                    <span class="block text-sm mb-1">ملاحظة القرار</span>
                    <textarea name="note" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">تسجيل القرار</button>
            </form>
        </x-modal>
    @endforeach
@endpush
