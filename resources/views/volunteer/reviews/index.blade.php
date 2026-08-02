@extends('layouts.app')

@section('title', 'بانتظار مراجعتي')

@section('content')
@php
    // أوّل دفعة صب-تاسكات في الطابور — زرّ «مراجعة دفعة» يفتحها مباشرةً
    $batchRow = $rows->first(fn ($row) => ($row['escalation']->case_type ?? null) === 'subtask_batch' && $row['task']);
@endphp

    <x-page-header
        title="بانتظار مراجعتي"
        subtitle="طابور ما ينتظر قرارك — بنافذة كلّ عنصر."
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/volunteer')], ['label' => 'بانتظار مراجعتي']]">
        @if ($batchRow)
            <x-slot:action>
                <a href="{{ route('volunteer.reviews.batch', $batchRow['task']) }}"
                   class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                   style="background: var(--color-brand-500); color: #04201c">مراجعة دفعة</a>
            </x-slot:action>
        @endif
    </x-page-header>

    {{-- تنبيه دائم: المراجِع مقيس ونافذته محسوبة (23 — القسم 5) --}}
    <p class="card p-3 mb-4 text-sm" style="border-inline-start: 3px solid var(--color-state-warn)">
        ▲ فوات النافذة يرفع الحالة لأبلاينك وعليه أثر التباطؤ.
    </p>

    {{-- 3 عدّادات ملوّنة + متوسّط زمن مراجعتي كمؤشّر عليّ (24.4) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-kpi label="داخل النافذة" :value="$counters['ok']" icon="●" state="ok" />
        <x-kpi label="اقتربت" :value="$counters['warn']" icon="▲" state="warn" />
        <x-kpi label="فاتت / تصعّد" :value="$counters['danger']" icon="◉" state="danger" />
        <x-kpi label="متوسّط زمن مراجعتي (ساعة)" :value="$averageHours ?? '—'" icon="clock"
               hint="مؤشّر عليك أنت — الساعة تقف لحظة تسليم المنفّذ لا لحظة اعتمادك." />
    </div>

    <x-filters :action="route('volunteer.reviews')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">النوع</span>
            <select name="kind" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($kinds as $key => $label)
                    <option value="{{ $key }}" @selected($filters['kind'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الشخص</span>
            <select name="person" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($people as $person)
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
        <x-empty message="مفيش حاجة تنتظر قرارك — كلّه تمام" />
    @else
        <div class="space-y-3">
            @foreach ($rows as $row)
                <article class="card p-4 animate-fadeup">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-bold truncate">{{ $row['title'] }}</h2>
                            <p class="text-xs mt-1" style="color: var(--text-muted)">
                                {{ $kinds[$row['kind']] ?? $row['kind'] }} · المُسلِّم: {{ $row['user']->name ?? '—' }}
                                @if ($row['task']) · المهمّة: {{ $row['task']->title }} @endif
                            </p>
                            @if ($row['returns'] > 0)
                                <p class="text-xs mt-1" style="color: var(--color-state-warn)">
                                    ▲ {{ $row['returns'] }} إرجاع سابق —
                                    @if ($row['returns'] >= $escalateAfter) القرار التالي يتصعّد. @else القرار التالي يقترب من التصعيد. @endif
                                </p>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <x-state-badge :state="$row['state']"
                                           :label="'نافذة '.$windowHours.'س: '.($row['due_at']?->format('Y-m-d H:i') ?? '—')" />
                        </div>
                    </div>

                    @if ($row['spec'])
                        <details class="mt-3">
                            <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">شكل المخرجات</summary>
                            <p class="text-sm mt-2 whitespace-pre-line">{{ $row['spec'] }}</p>
                        </details>
                    @endif

                    @if ($row['link'])
                        <a href="{{ $row['link'] }}" target="_blank" rel="noopener"
                           class="inline-block mt-2 text-sm underline">رابط المخرج</a>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($row['kind'] === 'task_submission')
                            <button type="button" data-modal-open="approve-task-{{ $row['id'] }}"
                                    class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                    style="background: var(--color-brand-500); color: #04201c">اعتماد</button>
                            <button type="button" data-modal-open="return-task-{{ $row['id'] }}"
                                    class="rounded-xl px-4 py-2 text-sm" style="border: 1px solid var(--border)">إرجاع</button>
                        @elseif ($row['kind'] === 'contribution')
                            <form method="post" action="{{ route('volunteer.reviews.contribution.approve', $row['id']) }}">
                                @csrf
                                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                        style="background: var(--color-brand-500); color: #04201c">اعتماد</button>
                            </form>
                            <button type="button" data-modal-open="return-contribution-{{ $row['id'] }}"
                                    class="rounded-xl px-4 py-2 text-sm" style="border: 1px solid var(--border)">إرجاع</button>
                        @else
                            <a href="{{ route('volunteer.escalations') }}"
                               class="rounded-xl px-4 py-2 text-sm" style="border: 1px solid var(--border)">افتح على المحرّك</a>
                            @if (($row['escalation']->case_type ?? null) === 'subtask_batch' && $row['task'])
                                <a href="{{ route('volunteer.reviews.batch', $row['task']) }}"
                                   class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                   style="background: var(--color-brand-500); color: #04201c">مراجعة دفعة</a>
                            @endif
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection

{{-- الفعل الرئيسيّ على الموبايل في متناول الإبهام (2.15-ج) --}}
@section('mobile_action')
    <a href="{{ route('volunteer.reviews', ['urgent' => 1]) }}"
       class="btn block text-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">
        ابدأ بالأقرب لانتهاء النافذة ({{ $counters['danger'] + $counters['warn'] }})
    </a>
@endsection

@push('modals')
    @foreach ($rows as $row)
        @if ($row['kind'] === 'task_submission')
            <x-modal :id="'approve-task-'.$row['id']" title="اعتماد التسليم">
                <form method="post" action="{{ route('volunteer.reviews.task.approve', $row['id']) }}" class="space-y-3">
                    @csrf
                    @if ($canLibrary)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="add_to_library" value="1" style="accent-color: var(--color-brand-500)">
                            أضِف المخرج للمكتبة الداخليّة
                        </label>
                        <label class="block text-sm">
                            <span class="block text-xs mb-1" style="color: var(--text-muted)">مستوى الوصول</span>
                            <select name="access_level" class="w-full rounded-xl px-3 py-2 text-sm"
                                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                @foreach ($accessLevels as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">اعتماد</button>
                </form>
            </x-modal>

            <x-modal :id="'return-task-'.$row['id']" title="إرجاع بفيدباك">
                @include('volunteer.reviews.partials.return-form', [
                    'action' => route('volunteer.reviews.task.return', $row['id']),
                    'reasons' => $reasons,
                    'fixHours' => $fixHours,
                ])
            </x-modal>
        @elseif ($row['kind'] === 'contribution')
            <x-modal :id="'return-contribution-'.$row['id']" title="إرجاع بند المساهمة">
                @include('volunteer.reviews.partials.return-form', [
                    'action' => route('volunteer.reviews.contribution.return', $row['id']),
                    'reasons' => $reasons,
                    'fixHours' => $fixHours,
                ])
            </x-modal>
        @endif
    @endforeach
@endpush
