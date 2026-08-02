{{-- الليدر بورد (7.3): النطاقات والفترات والتجميد --}}

<section class="card p-4 md:p-5">
    <h2 class="font-bold mb-1">النطاقات والفترات</h2>
    <p class="text-sm" style="color: var(--text-muted)">
        النطاقات المفعّلة (بالأيّام):
        <strong>{{ implode(' · ', (array) setting('leaderboard.ranges', [7, 30])) }}</strong>
        {{ setting('leaderboard.custom_range_enabled', true) ? '+ فترة مخصّصة' : '' }}
    </p>
    <p class="text-sm mt-2" style="color: var(--text-muted)">
        المقياس <strong>XP</strong> (قفل معلَن) · حدّ أدنى للمشاركين
        <strong>{{ setting('leaderboard.min_participants', 5) }}</strong> ·
        إعادة الاحتساب كلّ <strong>{{ setting('leaderboard.recalc_hours', 6) }}</strong> ساعة.
    </p>
</section>

<section class="card p-4 md:p-5 mt-4">
    <h2 class="font-bold mb-1">التجميد</h2>
    <div class="flex items-center gap-2 mt-1">
        <x-state-badge :state="setting('leaderboard.frozen', false) ? 'warn' : 'ok'"
                       :label="setting('leaderboard.frozen', false) ? 'اللوحة مجمَّدة الآن' : 'اللوحة تعمل'" />
        <span class="text-xs" style="color: var(--text-muted)">التجميد يوقف إعادة الاحتساب ويُبقي آخر ترتيب بتاريخه.</span>
    </div>
</section>

@can('leaderboards.view')
    @include('admin.volunteer.partials.settings-card', [
        'title' => 'إعدادات الليدر بورد',
        'rows' => $data['settings'],
        'action' => route('admin.gamification.settings.save'),
        'resetAction' => route('admin.gamification.reset'),
        'resetPayload' => ['group' => 'gamification_leaderboard'],
        'open' => true,
    ])
@endcan
