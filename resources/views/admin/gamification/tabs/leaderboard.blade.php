{{-- الليدر بورد (7.3): النطاقات والفترات والتجميد --}}

<section class="card p-4 md:p-5">
    <h2 class="font-bold mb-1">{{ setting('admin.gamification.tabs.leaderboard.alntaqat_walftrat', 'النطاقات والفترات') }}</h2>
    <p class="text-sm" style="color: var(--text-muted)">
        {{ setting('admin.gamification.tabs.leaderboard.alntaqat_almfala_balayam', 'النطاقات المفعّلة (بالأيّام):') }}
        <strong>{{ implode(' · ', (array) setting('leaderboard.ranges', [7, 30])) }}</strong>
        {{ setting('leaderboard.custom_range_enabled', true) ? setting('admin.gamification.tabs.leaderboard.ftra_mkhssa', '+ فترة مخصّصة') : '' }}
    </p>
    <p class="text-sm mt-2" style="color: var(--text-muted)">
        {{ setting('admin.gamification.tabs.leaderboard.almqyas', 'المقياس') }} <strong>XP</strong> {{ setting('admin.gamification.tabs.leaderboard.qfl_maln_hd_adna_llmsharkyn', '(قفل معلَن) · حدّ أدنى للمشاركين') }}
        <strong>{{ setting('leaderboard.min_participants', 5) }}</strong> {{ setting('admin.gamification.tabs.leaderboard.iaada_alahtsab_kl', '· إعادة الاحتساب كلّ') }} <strong>{{ setting('leaderboard.recalc_hours', 6) }}</strong> {{ setting('admin.gamification.tabs.leaderboard.saaa', 'ساعة.') }}
    </p>
</section>

<section class="card p-4 md:p-5 mt-4">
    <h2 class="font-bold mb-1">{{ setting('admin.gamification.tabs.leaderboard.altjmyd', 'التجميد') }}</h2>
    <div class="flex items-center gap-2 mt-1">
        <x-state-badge :state="setting('leaderboard.frozen', false) ? 'warn' : 'ok'"
                       :label="setting('leaderboard.frozen', false) ? setting('admin.gamification.tabs.leaderboard.allwha_mjmda_alan', 'اللوحة مجمَّدة الآن') : setting('admin.gamification.tabs.leaderboard.allwha_taml', 'اللوحة تعمل')" />
        <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.gamification.tabs.leaderboard.altjmyd_ywqf_iaada_alahtsab_wybqy_akhr_trtyb', 'التجميد يوقف إعادة الاحتساب ويُبقي آخر ترتيب بتاريخه.') }}</span>
    </div>
</section>

@can('leaderboards.view')
    @include('admin.volunteer.partials.settings-card', [
        'title' => setting('admin.gamification.tabs.leaderboard.iadadat_allydr_bwrd', 'إعدادات الليدر بورد'),
        'rows' => $data['settings'],
        'action' => route('admin.gamification.settings.save'),
        'resetAction' => route('admin.gamification.reset'),
        'resetPayload' => ['group' => 'gamification_leaderboard'],
        'open' => true,
    ])
@endcan
