@extends('layouts.volunteer')

@section('title', setting('volunteer.escalations.title', 'يحتاج قرارك'))

@section('content')
    <x-page-header
        :title="setting('volunteer.escalations.title', 'يحتاج قرارك')"
        :subtitle="setting('volunteer.escalations.subtitle', 'كلّ حالة معلّقة على مكتبك بنافذتها قبل أن تصعد.')"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer')], ['label' => setting('volunteer.escalations.title', 'يحتاج قرارك')]]" />

    <p class="card p-3 mb-4 text-sm" style="border-inline-start: 3px solid var(--color-state-warn)">
        ▲ {{ str_replace(':rep', rep_rule('task.slowdown'), (string) setting('volunteer.escalations.text', 'فوات نافذتك يرفع الحالة لأبلاينك وعليك أثر التباطؤ (:rep).')) }}
    </p>

    <div class="grid grid-cols-3 gap-3 mb-4">
        <x-kpi :label="setting('volunteer.escalations.label', 'داخل النافذة')" :value="$counters['ok']" icon="●" state="ok" />
        <x-kpi :label="setting('volunteer.escalations.label_2', 'اقتربت')" :value="$counters['warn']" icon="▲" state="warn" />
        <x-kpi :label="setting('volunteer.escalations.label_3', 'فاتت')" :value="$counters['danger']" icon="◉" state="danger" />
    </div>

    <x-filters :action="route('volunteer.escalations')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.escalations.field', 'نوع الحالة') }}</span>
            <select name="case_type" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                @foreach ($catalog as $type => $meta)
                    <option value="{{ $type }}" @selected($filters['case_type'] === $type)>{{ $meta['icon'] }} {{ $meta['label'] }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.escalations.field_2', 'الشخص') }}</span>
            <select name="person" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('volunteer.common.all', 'الكلّ') }}</option>
                @foreach ($requesters as $person)
                    <option value="{{ $person->id }}" @selected($filters['person'] === $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="urgent" value="1" @checked($filters['urgent'])
                   onchange="this.form.submit()" style="accent-color: var(--color-brand-500)">
            {{ setting('volunteer.escalations.field_3', 'الأقرب لانتهاء النافذة') }}
        </label>
    </x-filters>

    @if ($rows->isEmpty())
        <x-empty :message="setting('volunteer.escalations.empty', 'مكتبك فاضي — كلّه تمام')" />
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
                                {{ $requesters[$case->requested_by]->name ?? setting('volunteer.escalations.text_2', 'النظام') }}
                                @if ($subject?->title) · {{ $subject->title }} @endif
                                · {{ setting('volunteer.escalations.field_4', 'المستوى') }} {{ $case->level }}
                            </p>
                            @if (! empty($payload['note']))
                                <p class="text-sm mt-2">{{ $payload['note'] }}</p>
                            @endif
                        </div>

                        <div class="flex flex-col items-end gap-2 shrink-0">
                            <x-state-badge :state="$state"
                                           :label="($case->is_top_level ? setting('volunteer.escalations.label_4', 'نافذة السقف 48س') : setting('volunteer.escalations.label_5', 'نافذة 24س')).': '.$case->window_due_at?->format('Y-m-d H:i')" />
                        </div>
                    </div>

                    {{-- التسوية الآليّة المنتظَرة مكتوبة صراحةً — الشفافيّة نفسها رادع --}}
                    <p class="text-xs mt-3" style="color: var(--text-muted)">
                        {{ setting('volunteer.escalations.field_5', 'لو فاتت نافذة السقف:') }} <strong>{{ $meta['settlement_label'] ?? '—' }}</strong>
                        @if (! empty($meta['notice'])) · {{ $meta['notice'] }} @endif
                    </p>

                    <div class="mt-3">
                        <button type="button" data-modal-open="decide-{{ $case->id }}"
                                class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.escalations.action', 'اتّخِذ قرارك') }}</button>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('volunteer.escalations', ['urgent' => 1]) }}"
       class="btn block text-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">
        {{ str_replace(':count', $counters['danger'] + $counters['warn'], (string) setting('volunteer.escalations.link', 'الحالات الملحّة (:count)')) }}
    </a>
@endsection

@push('modals')
    @foreach ($rows as $case)
        @php $meta = $catalog[$case->case_type] ?? []; @endphp

        <x-modal :id="'decide-'.$case->id" :title="($meta['label'] ?? $case->case_type).setting('volunteer.escalations.tooltip', ' — قرارك')">
            <form method="post" action="{{ route('volunteer.escalations.decide', $case) }}" class="space-y-3">
                @csrf

                @if (! empty($meta['notice']))
                    <p class="rounded-xl px-3 py-2 text-sm"
                       style="background: color-mix(in srgb, var(--color-state-ok) 12%, transparent)">
                        ● {{ $meta['notice'] }}
                    </p>
                @endif

                <fieldset class="space-y-2">
                    <legend class="text-sm mb-1">{{ setting('volunteer.escalations.legend', 'القرار') }}</legend>
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
                    <x-form.input name="rep_value" :label="setting('volunteer.escalations.label_6', 'قيمة Rep اليدويّة')" type="number" step="0.05"
                                  :min="$repMin" :max="$repMax"
                                  :hint="setting('volunteer.escalations.hint', 'محصورة بين ').$repMax.' و'.$repMin.setting('volunteer.escalations.hint_2', ' — وتُطبَّق مع «إنهاء» فقط.')" />
                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('volunteer.escalations.field_6', 'المبرّر') }} <span style="color: var(--color-state-danger)">*</span></span>
                        <textarea name="justification" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                @endif

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('volunteer.escalations.field_7', 'ملاحظة القرار') }}</span>
                    <textarea name="note" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.escalations.action_2', 'تسجيل القرار') }}</button>
            </form>
        </x-modal>
    @endforeach
@endpush
