{{-- تاب «تفاصيل»: كلّ رقم زائد عن الأربعة ينتقل هنا (2.15-أ-3) --}}

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($details['cards'] as $card)
        @include('admin.dashboard.components.kpi', ['card' => $card, 'compare' => $compare])
    @endforeach
</div>

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
