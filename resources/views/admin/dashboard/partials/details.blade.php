{{-- تاب «تفاصيل»: كلّ كارت زائد عن الأربعة **يُنقَل هنا لا يُحذَف** (2.15-أ-3) --}}

@if ($details['cards'] !== [])
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($details['cards'] as $card)
            @include('admin.dashboard.components.kpi', ['card' => $card, 'compare' => $compare])
        @endforeach
    </div>
@endif

{{-- 🔒 الهدف الشهريّ ببار تقدّم (12.3-10) — رقمٌ ماليّ فلمالك المنصّة وحده --}}
@if ($details['target'])
    @php $target = $details['target']; @endphp
    <section class="card p-4 mt-6">
        <div class="flex items-baseline justify-between gap-2 flex-wrap">
            <h3 class="font-bold text-sm">🔒 {{ $target['label'] }} — {{ $target['month'] }}</h3>
            <span class="text-xs" style="color: var(--text-muted)">
                {{ number_format($target['achieved']) }} من {{ number_format($target['target']) }}
                ({{ $target['percent'] }}%)
            </span>
        </div>

        @if ($target['target'] > 0)
            {{-- بار التقدّم رقمٌ مكتوب معه دائمًا — اللون لا يحمل المعنى وحده (2.16) --}}
            <div class="mt-3 h-3 w-full rounded-full overflow-hidden" style="background: var(--surface-sunken)"
                 role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $target['percent'] }}"
                 aria-label="{{ $target['label'] }}">
                <div class="h-full rounded-full" style="width: {{ $target['percent'] }}%; background: var(--color-brand-500)"></div>
            </div>
        @else
            <p class="mt-3 text-sm" style="color: var(--text-muted)">
                {{ setting('admin.dashboard.target_empty_text', 'ماحدّدتش هدفًا للشهر لسّه — اضبطه من إعدادات لوحة القيادة.') }}
            </p>
        @endif
    </section>
@endif

<div class="mt-6 grid gap-4 lg:grid-cols-2">
    @include('admin.dashboard.components.chart-funnel', ['stages' => $details['funnel']])

    {{-- أبرز المؤثّرين: أعلى الدعوات (12.3-12) --}}
    <section class="card p-4 min-w-0">
        <h3 class="font-bold text-sm">أبرز المؤثّرين</h3>

        @if ($details['topReferrers']->isEmpty())
            <p class="mt-4 text-sm" style="color: var(--text-muted)">لسّه بدري — أوّل دعوة مستنّياك.</p>
        @else
            <ul class="mt-3 divide-y" style="border-color: var(--border)">
                @foreach ($details['topReferrers'] as $row)
                    <li class="flex items-center gap-3 py-2" style="border-color: var(--border)">
                        <x-avatar :user="$row['user']" size="8" />
                        <span class="min-w-0 flex-1 truncate text-sm">{{ $row['user']->shortName() }}</span>
                        <span class="text-xs" style="color: var(--text-muted)">{{ $row['invites'] }} دعوة</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-2">
    {{-- خريطة حراريّة جغرافيّة (12.3-13) — والحرارة **رقم ونسبة مكتوبان** لا لونًا وحده (2.16) --}}
    @include('admin.dashboard.components.heat-rows', [
        'title' => 'الخريطة الحراريّة الجغرافيّة',
        'rows' => $details['geo'],
        'empty' => 'مافيش بيانات جغرافيّة لسّه — الدولة بتتسجّل مع الحساب.',
        'unit' => 'مستخدم',
    ])

    {{-- أكثر التدريبات تعثّرًا (12.3-15) --}}
    @include('admin.dashboard.components.heat-rows', [
        'title' => 'أكثر التدريبات تعثّرًا (Drop-off)',
        'rows' => $details['dropoff'],
        'empty' => 'مافيش تعثّر مسجَّل — كلّ اللي سجّلوا خلّصوا.',
        'unit' => 'متدرّب',
    ])
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-2">
    {{-- صحّة التلعيب (12.3-14) --}}
    <section class="card p-4 min-w-0">
        <h3 class="font-bold text-sm">صحّة التلعيب</h3>

        <ul class="mt-3 divide-y" style="border-color: var(--border)">
            @foreach ($details['gamification'] as $row)
                <li class="flex items-center justify-between gap-3 py-2" style="border-color: var(--border)">
                    <span class="text-sm">{{ $row['label'] }}</span>
                    <span class="text-sm font-bold cursor-help" title="{{ $row['hint'] }}"
                          data-count-to="{{ number_format($row['value']) }}">{{ number_format($row['value']) }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- الحروب الجارية (12.3-11 — نبض المجتمع لحظيًّا) --}}
    <section class="card p-4 min-w-0">
        <h3 class="font-bold text-sm">الحروب الجارية</h3>

        @if ($details['wars'] === [])
            <p class="mt-4 text-sm" style="color: var(--text-muted)">مافيش حرب شغّالة دلوقتي.</p>
        @else
            <ul class="mt-3 divide-y" style="border-color: var(--border)">
                @foreach ($details['wars'] as $row)
                    <li class="flex items-center justify-between gap-3 py-2" style="border-color: var(--border)">
                        <span class="min-w-0 flex-1 truncate text-sm">{{ $row['label'] }}</span>
                        <span class="text-xs" style="color: var(--text-muted)">{{ number_format($row['value']) }} مشارك الآن</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

{{-- 🔒 أعلى التدريبات/المنتجات مبيعًا (12.3-6) — ماليّ فلمالك المنصّة وحده --}}
@if ($details['topSelling']->isNotEmpty())
    <section class="card p-4 mt-6 min-w-0">
        <h3 class="font-bold text-sm">🔒 الأعلى مبيعًا</h3>

        <ul class="mt-3 divide-y" style="border-color: var(--border)">
            @foreach ($details['topSelling'] as $row)
                <li class="flex items-center justify-between gap-3 py-2" style="border-color: var(--border)">
                    <span class="min-w-0 flex-1 truncate text-sm">{{ $row['label'] }}</span>
                    <span class="text-xs" style="color: var(--text-muted)">
                        {{ number_format($row['count']) }} طلب · {{ number_format($row['value']) }}
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
