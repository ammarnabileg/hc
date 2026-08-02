@php
    /** تاب «الهيكل التنظيميّ» (13.4-م-3) — والمؤشّر الأحمر لا يُعرَض هنا (13.4-هـ). */
    $g = $panel;
    $dateFormat = (string) setting('volunteer.org.date_format', 'j F Y');
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <x-kpi label="القسم" :value="$g['entity'] ?? '—'" icon="entity" :hint="$g['parent_entity']" />
    <x-kpi label="البوزشن" :value="$g['position'] ?? '—'" icon="badge" />
    <x-kpi label="تاريخ التسكين" :value="$g['placed_at']?->translatedFormat($dateFormat) ?? '—'" icon="calendar" />
    <x-kpi label="شبكتك الكاملة" :value="$g['network_total']" icon="people" hint="كلّ المستويات تحتك" />
</div>

<div class="grid md:grid-cols-2 gap-3">

    {{-- تايم-لاين البوزشنز بتواريخ كلّ انتقال — ومنه تُصدَر شهادة البوزشن --}}
    <section class="card p-4 animate-fadeup">
        <h2 class="font-bold text-sm mb-3">تايم-لاين البوزشنز</h2>
        @if ($g['timeline']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">لسّه بدري — أوّل بوزشن مستنّيك.</p>
        @else
            <ol class="space-y-3">
                @foreach ($g['timeline'] as $step)
                    <li class="flex items-start gap-3">
                        <span class="mt-1 w-2 h-2 rounded-full shrink-0"
                              style="background: var(--color-{{ $step['is_current'] ? 'brand-500' : 'state-idle' }})"></span>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold">
                                {{ $step['position'] }}
                                @if ($step['entity']) <span style="color: var(--text-muted)">· {{ $step['entity'] }}</span> @endif
                                @if ($step['is_acting']) <x-state-badge state="warn" label="قائم بأعمال" /> @endif
                            </div>
                            <div class="text-xs" style="color: var(--text-muted)">
                                {{ $step['from']?->translatedFormat($dateFormat) }}
                                — {{ $step['to']?->translatedFormat($dateFormat) ?? 'دلوقتي' }}
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- سلسلة الأبلاين كاملة لأعلى — نفس منطق سلّم التصعيد المرئيّ (13.4-ط) --}}
    <section class="card p-4">
        <h2 class="font-bold text-sm mb-3">سلسلة الأبلاين لأعلى</h2>
        @if ($g['upline_chain']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">مفيش أبلاين فوقه — ده أعلى الهيكل.</p>
        @else
            <ol class="space-y-2">
                @foreach ($g['upline_chain'] as $node)
                    <li class="flex items-center justify-between gap-2 text-sm">
                        <span class="min-w-0 truncate">
                            @if ($node['profile_url'])
                                <a href="{{ $node['profile_url'] }}" style="color: var(--color-brand-500)">{{ $node['name'] }}</a>
                            @else
                                {{ $node['name'] }}
                            @endif
                            <span style="color: var(--text-muted)">· {{ $node['position'] }}</span>
                        </span>
                        @if ($node['is_direct'])
                            <x-state-badge state="ok" label="الأبلاين المباشر" />
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- الداونلاين: كروت مضغوطة هنا، والكانفاس القائم هو مصدر الشكل الكامل (13.4-م-3) --}}
    <section class="card p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-bold text-sm">فريقه (الداونلاين)</h2>
            @if ($g['can_open_canvas'])
                <a href="{{ $g['canvas_url'] }}" class="text-xs" style="color: var(--color-brand-500)">افتح الكانفاس</a>
            @endif
        </div>
        @if ($g['downline']->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">مفيش حدّ تحته دلوقتي.</p>
        @else
            <ul class="space-y-2 text-sm">
                @foreach ($g['downline'] as $member)
                    <li class="flex items-center justify-between gap-2">
                        <a href="{{ $member['profile_url'] }}" class="min-w-0 truncate" style="color: var(--color-brand-500)">{{ $member['name'] }}</a>
                        <span class="text-xs" style="color: var(--text-muted)">{{ $member['position'] }} · تحته {{ $member['load'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- سجلّ الحركات التنظيميّة: بمَن نفّذها ومتى — للمخوَّل وحده --}}
    @if ($g['shows_movements'])
        <section class="card p-4">
            <h2 class="font-bold text-sm mb-3">سجلّ الحركات التنظيميّة</h2>
            @if ($g['movements']->isEmpty())
                <p class="text-sm" style="color: var(--text-muted)">مفيش حركات مسجّلة.</p>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($g['movements'] as $row)
                        <li class="flex items-center justify-between gap-2">
                            <span class="min-w-0 truncate">{{ $row->action }}</span>
                            <span class="text-xs" style="color: var(--text-muted)"
                                  title="{{ $row->created_at->format('Y-m-d H:i') }}">
                                {{ $row->user?->shortName() ?? 'النظام' }} · {{ $row->created_at->diffForHumans() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</div>
