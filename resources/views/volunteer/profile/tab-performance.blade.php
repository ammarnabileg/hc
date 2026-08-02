@php
    /** تاب «الأداء» (13.4-م-4) — والتقييمات متوسّطات مجهولة دائمًا (13.4-ي). */
    $p = $panel;
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <x-kpi label="درجة الالتزام" :value="$p['score']" icon="⚖️" :state="$p['state']" />
    <x-kpi label="رصيد VXP" :value="$p['vxp_balance']" icon="⚡" />
    <x-kpi label="متوسّط التقييم"
           :value="$p['evaluations']['average'] !== null ? $p['evaluations']['average'].' / '.$p['evaluations']['scale'] : 'لسّه بدري'"
           icon="⭐" />
    <x-kpi label="حركات الفترة" :value="$p['movements']->count()" icon="🔁" :hint="'آخر '.$p['days'].' يوم'" />
</div>

<div class="grid md:grid-cols-2 gap-3 mb-3">
    @include('volunteer.profile.partials.sparkline', [
        'title' => 'درجة الالتزام',
        'series' => $p['rep_series'],
        'days' => $p['days'],
        'tone' => 'var(--color-brand-500)',
    ])
    @include('volunteer.profile.partials.sparkline', [
        'title' => 'تراكم VXP',
        'series' => $p['vxp_series'],
        'days' => $p['days'],
        'tone' => 'var(--color-state-ok)',
    ])
</div>

{{-- ⭐ مؤشّر مخاطر الفقدان: للأبلاين المخوَّل فقط — ولا يُعرَض للمتطوّع عن نفسه أبدًا --}}
@if ($p['retention_risk'])
    <section class="card p-4 mb-3">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <h2 class="font-bold text-sm">مؤشّر مخاطر الفقدان</h2>
            <x-state-badge :state="$p['retention_risk']['at_risk'] ? 'danger' : 'ok'"
                           :label="$p['retention_risk']['at_risk'] ? 'يحتاج تدخّل مبكّر' : 'مستقرّ'" />
        </div>
        <p class="text-sm">
            درجة الالتزام {{ $p['retention_risk']['rep'] }} ·
            مهامّ متأخّرة {{ $p['retention_risk']['late_tasks'] }} ·
            {{ $p['retention_risk']['idle'] ? 'خمول ملحوظ' : 'نشاط منتظم' }}
        </p>
        <p class="mt-2 text-xs" style="color: var(--text-muted)">{{ $p['retention_risk']['note'] }}</p>
    </section>
@endif

<div class="grid md:grid-cols-2 gap-3">

    {{-- التقييمات **كمتوسّطات مجهولة** — بلا أسماء ولا درجات فرديّة (13.4-ي) --}}
    <section class="card p-4">
        <h2 class="font-bold text-sm mb-3">التقييمات الأسبوعيّة</h2>
        @if (! $p['evaluations']['visible'])
            <p class="text-sm" style="color: var(--text-muted)">
                لسّه بدري — المتوسّط بيبان بعد {{ $p['evaluations']['min_raters'] }} تقييمات على الأقلّ.
            </p>
        @else
            <div class="text-2xl font-extrabold" data-count-to="{{ $p['evaluations']['average'] }}">{{ $p['evaluations']['average'] }}</div>
            <p class="text-xs" style="color: var(--text-muted)">من {{ $p['evaluations']['scale'] }} · {{ $p['evaluations']['raters'] }} تقييم</p>
        @endif
        <p class="mt-3 text-xs" style="color: var(--text-muted)">{{ $p['evaluations']['note'] }}</p>
    </section>

    {{-- التطوّر الشهريّ — الاتّجاه لا الرقم المجرّد --}}
    <section class="card p-4">
        <h2 class="font-bold text-sm mb-3">التطوّر الشهريّ</h2>
        <ul class="space-y-2 text-sm">
            @foreach ($p['monthly'] as $month)
                <li class="flex items-center justify-between gap-2">
                    <span>{{ $month['label'] }}</span>
                    <span class="font-bold">{{ $month['value'] > 0 ? '+' : '' }}{{ $month['value'] }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="card p-4 md:col-span-2">
        <h2 class="font-bold text-sm mb-3">المعاملات المؤثّرة</h2>
        @if ($p['movements']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">مفيش حركات في الفترة دي.</p>
        @else
            {{-- على الموبايل كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
            <ul class="space-y-2">
                @foreach ($p['movements'] as $movement)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="min-w-0 truncate">{{ $movement->reason ?: $movement->source }}</span>
                        <span class="flex items-center gap-2">
                            <x-state-badge :state="(float) ($movement->applied_amount ?? $movement->amount) >= 0 ? 'ok' : 'danger'"
                                           :label="(string) round((float) ($movement->applied_amount ?? $movement->amount), 2)" />
                            <span class="text-xs" style="color: var(--text-muted)"
                                  title="{{ $movement->created_at->format('Y-m-d') }}">{{ $movement->created_at->diffForHumans() }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
