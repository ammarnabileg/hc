{{-- الستريكس ونادي الخامسة (7.2): الشروط والمكافآت --}}

<section class="card p-4 md:p-5">
    <h2 class="font-bold mb-1">نافذة نادي الخامسة</h2>
    <p class="text-sm" style="color: var(--text-muted)">
        من <strong>{{ setting('streaks.club5am.window_start', '04:50') }}</strong>
        إلى <strong>{{ setting('streaks.club5am.window_end', '05:20') }}</strong>
        بتوقيت المستخدم المحلّيّ حسب دولته — والأيّام <strong>ليست شرطًا أن تكون متتابعة</strong>.
    </p>
</section>

<section class="card p-4 md:p-5 mt-4">
    <h2 class="font-bold mb-1">سلّم XP الحضور المتدرّج</h2>
    <p class="text-xs mb-3" style="color: var(--text-muted)">الصفوف غير محدودة — تُحرَّر من بلوك الإعدادات كـJSON.</p>

    @forelse ($data['ladder'] as $row)
        <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
            <span>
                من اليوم {{ $row['from'] ?? '—' }}
                @if (($row['to'] ?? 0) > 0) إلى {{ $row['to'] }} @else فما فوق @endif
            </span>
            <span class="font-bold">{{ $row['xp'] ?? 0 }} XP</span>
        </div>
    @empty
        <x-empty message="السلّم فاضي — حمّل الافتراضيّات من زرّ الـReset." />
    @endforelse
</section>

<section class="card p-4 md:p-5 mt-4">
    <h2 class="font-bold mb-1">الستريك ودرعه</h2>
    <ul class="text-sm space-y-1" style="color: var(--text-muted)">
        <li>• <strong>{{ setting('streaks.reward_days', 7) }}</strong> أيّام متواصلة ⟵ زرّ مكافأة يمنح
            <strong>{{ setting('streaks.reward_tickets', 1) }}</strong> تذكرة هدية إلى صندوق التذاكر.</li>
        <li>• تجميد الستريك = <strong>{{ (int) app(\App\Services\Gamification\StreakService::class)->freezeCost() }}</strong> تذكرة تحمي يومًا فايتًا،
            بأقصى <strong>{{ setting('streaks.max_freezes_per_month', 2) }}</strong> تجميدات شهريًّا،
            ولا يُحمى يوم أقدم من <strong>{{ setting('streaks.freeze_max_age_days', 2) }}</strong> أيّام.</li>
        <li>• مستوى الاحتفال: <strong>{{ setting('streaks.celebration_tier', 2) }}</strong> (متوسّط).</li>
    </ul>
</section>

@can('xp_rules.edit')
    @include('admin.volunteer.partials.settings-card', [
        'title' => 'إعدادات الستريكس ونادي الخامسة',
        'rows' => $data['settings'],
        'action' => route('admin.gamification.settings.save'),
        'resetAction' => route('admin.gamification.reset'),
        'resetPayload' => ['group' => 'gamification_streaks'],
        'open' => true,
    ])
@endcan
