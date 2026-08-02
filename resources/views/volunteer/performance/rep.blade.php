@extends('layouts.app')

@section('title', 'درجة الالتزام')

@php
    /**
     * درجة الالتزام (24.4 · 13.4-ن).
     * كلّ رقم في هذه الشاشة مقروء من `rep_rule()` أو `setting()` — لا قيمة محروقة واحدة.
     */
    $fmt = fn ($v) => ($v > 0 ? '+' : '').rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp

@section('content')
    <x-page-header
        title="درجة الالتزام"
        subtitle="رقمك الحاليّ، ومن أين جاء، وإلى أين يتّجه."
        :breadcrumbs="[['label' => 'الأداء', 'url' => route('volunteer.performance.vxp')], ['label' => 'درجة الالتزام']]" />

    @if ($isRed)
        {{-- ⭐ بانر إنذار هادئ بلا فضح عند تخطّي المؤشّر الأحمر (24.4) --}}
        <div class="card p-4 mb-4" style="border: 1px solid var(--color-state-warn)">
            <p class="text-sm leading-relaxed">
                رقمك دلوقتي تحت {{ $fmt($red) }} — أبلاينك هيتواصل معك خلال 48 ساعة عشان نشوف الصورة كاملة ونظبّطها سوا.
            </p>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
        @include('volunteer.performance.partials.gauge', [
            'score' => $score,
            'min' => $bounds['min'],
            'max' => $bounds['max'],
            'state' => $state,
            'marks' => [
                ['value' => $warning, 'label' => $fmt($warning), 'color' => 'warn'],
                ['value' => $red, 'label' => $fmt($red), 'color' => 'danger'],
            ],
        ])

        <div class="md:col-span-2 grid grid-cols-1 gap-3">
            <div class="card p-4">
                <div class="text-sm" style="color: var(--text-muted)">يتصفّر في</div>
                <div class="text-lg font-bold mt-1">{{ $nextReset->format('Y/m/d') }} الساعة {{ $nextReset->format('H:i') }}</div>
                <p class="text-xs mt-1" style="color: var(--text-muted)">
                    يوم {{ (int) setting('rep.reset.day_of_month', 1) }} الساعة {{ (int) setting('rep.reset.hour', 5) }}:00ص بتوقيت القاهرة —
                    الرقم الظاهر بيرجع صفر، والسجلّ والمكتسَب التراكميّ يفضلوا زيّ ما هم.
                </p>
            </div>

            {{-- ⭐ كارت حدّ الخسارة اليوميّ (13.4-ن-و) --}}
            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold">حدّ الخسارة اليوميّ {{ $fmt($dailyCap) }}</span>
                    <x-state-badge :state="$lostToday <= $dailyCap ? 'danger' : 'ok'"
                                   :label="'نزل النهارده '.$fmt($lostToday)" />
                </div>

                @forelse ($exceeded as $transaction)
                    <div class="mt-2 rounded-xl p-2 text-xs" style="background: var(--surface-raised)">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <span>{{ $transaction->reason ?: $transaction->source }}</span>
                            <span style="color: var(--color-state-danger)">{{ $fmt($transaction->amount) }}</span>
                        </div>
                        <p class="mt-1" style="color: var(--color-state-warn)">
                            ▲ تخطّت حدّ الخسارة اليوميّ — مسجَّلة كاملةً في السجلّ
                            (نزل منها {{ $fmt($transaction->applied_amount) }}، والباقي {{ rtrim(rtrim(number_format($unapplied[$transaction->id] ?? 0, 2), '0'), '.') }} في المكتسَب التراكميّ).
                        </p>
                    </div>
                @empty
                    <p class="text-xs mt-2" style="color: var(--text-muted)">مفيش حركة تخطّت الحدّ النهارده.</p>
                @endforelse
            </div>
        </div>
    </div>

    <x-filters :action="route('volunteer.performance.rep')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">المصدر</span>
            <select name="source" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($sources as $key => $label)
                    <option value="{{ $key }}" @selected($filters['source'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الكيان</span>
            <select name="entity" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($memberships as $membership)
                    <option value="{{ $membership->entity_id }}" @selected($filters['entity'] === (int) $membership->entity_id)>
                        {{ $membership->entity?->name_ar }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الفترة</span>
            <select name="days" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                @foreach ([30 => '30 يومًا', 90 => '90 يومًا'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['days'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </x-filters>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        <div class="lg:col-span-2 space-y-3">
            @include('volunteer.performance.partials.line-chart', [
                'series' => $series,
                'title' => 'منحنى Rep اليوميّ',
                'chartId' => 'rep-curve',
                'zeroLine' => true,
            ])

            @if ($movements->isEmpty())
                <x-empty message="الشهر بدأ من الصفر للجميع" action="شوف نوبتي" :href="route('volunteer.recurring')" />
            @else
                <div class="card overflow-hidden">
                    <div class="hidden md:grid grid-cols-5 gap-2 px-4 py-2 text-xs" style="color: var(--text-muted)">
                        <span>التاريخ</span><span>المصدر</span><span>القيمة</span><span>المرجع</span><span>إجراء</span>
                    </div>

                    @foreach ($movements as $movement)
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-2 items-center px-4 py-3"
                             style="border-top: 1px solid var(--border)">
                            <span class="text-xs">{{ \Illuminate\Support\Carbon::parse($movement->created_at)->format('Y/m/d H:i') }}</span>
                            <span class="text-xs">{{ $sources[$movement->source] ?? $movement->source }}</span>
                            <span class="text-sm font-semibold"
                                  style="color: var(--color-state-{{ (float) $movement->amount >= 0 ? 'ok' : 'danger' }})">
                                {{ $fmt($movement->amount) }}
                            </span>
                            <span class="text-xs truncate" style="color: var(--text-muted)">{{ $movement->reason ?: '—' }}</span>
                            <span class="text-xs">
                                @if ($canObject[$movement->id] ?? false)
                                    <button type="button" data-modal-open="object-{{ $movement->id }}"
                                            class="btn rounded-lg px-3 py-1 motion-standard"
                                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                                        اعتراض
                                    </button>
                                @else
                                    <span style="color: var(--text-muted)">انتهت مهلة الاعتراض</span>
                                @endif
                            </span>

                            @if ($movement->exceeded_daily_cap)
                                <p class="md:col-span-5 text-xs" style="color: var(--color-state-warn)">
                                    ▲ تخطّت حدّ الخسارة اليوميّ — مسجَّلة كاملةً
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- كارت «كيف تكسب» — القيم من rep_rule() لا محروقة (2.13) --}}
        <aside class="card p-4 h-fit">
            <h2 class="text-sm font-semibold mb-1">كيف تكسب</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted)">القيم دي من لوحة الإدارة — بتتغيّر هنا لحظة ما تتغيّر هناك.</p>

            <div class="space-y-1">
                @foreach ($howToEarn as $rule)
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="min-w-0 truncate">{{ $rule['label'] }}</span>
                        <span class="font-semibold shrink-0"
                              style="color: var(--color-state-{{ $rule['value'] > 0 ? 'ok' : ($rule['value'] < 0 ? 'danger' : 'idle') }})">
                            {{ $fmt($rule['value']) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </aside>
    </div>

    @push('modals')
        @foreach ($movements as $movement)
            @continue(! ($canObject[$movement->id] ?? false))
            <x-modal :id="'object-'.$movement->id" title="اعتراض على معاملة">
                <form method="post" action="{{ route('volunteer.performance.rep.object', $movement) }}" class="space-y-3">
                    @csrf
                    <div class="rounded-xl p-3 text-sm" style="background: var(--surface-raised)">
                        <div>{{ $movement->reason ?: $movement->source }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted)">
                            القيمة {{ $fmt($movement->amount) }} · مهلة الاعتراض {{ $objectionDays }} أيّام من تاريخ الحركة
                        </div>
                    </div>

                    <textarea name="reason" rows="3" required minlength="10" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"
                              placeholder="اكتب سبب اعتراضك…"></textarea>

                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c; min-height: 44px">إرسال الاعتراض</button>
                </form>
            </x-modal>
        @endforeach
    @endpush
@endsection
