{{-- الستريكس ونادي الخامسة (7.2): الشروط والمكافآت --}}

<section class="card p-4 md:p-5">
    <h2 class="font-bold mb-1">{{ setting('admin.gamification.tabs.streaks.nafdha_nady_alkhamsa', 'نافذة نادي الخامسة') }}</h2>
    <p class="text-sm" style="color: var(--text-muted)">
        {{ setting('admin.gamification.tabs.streaks.mn', 'من') }} <strong>{{ setting('streaks.club5am.window_start', '04:50') }}</strong>
        {{ setting('admin.gamification.tabs.streaks.ila', 'إلى') }} <strong>{{ setting('streaks.club5am.window_end', '05:20') }}</strong>
        {{ setting('admin.gamification.tabs.streaks.btwqyt_almstkhdm_almhly_hsb_dwlth_walayam', 'بتوقيت المستخدم المحلّيّ حسب دولته — والأيّام') }} <strong>{{ setting('admin.gamification.tabs.streaks.lyst_shrta_an_tkwn_mttabaa', 'ليست شرطًا أن تكون متتابعة') }}</strong>.
    </p>
</section>

<section class="card p-4 md:p-5 mt-4">
    <h2 class="font-bold mb-1">{{ setting('admin.gamification.tabs.streaks.slm_xp_alhdwr_almtdrj', 'سلّم XP الحضور المتدرّج') }}</h2>
    <p class="text-xs mb-3" style="color: var(--text-muted)">{{ setting('admin.gamification.tabs.streaks.alsfwf_ghyr_mhdwda_thrr_mn_blwk_aliadadat', 'الصفوف غير محدودة — تُحرَّر من بلوك الإعدادات كـJSON.') }}</p>

    @forelse ($data['ladder'] as $row)
        <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
            <span>
                {{ setting('admin.gamification.tabs.streaks.mn_alywm', 'من اليوم') }} {{ $row['from'] ?? '—' }}
                @if (($row['to'] ?? 0) > 0) {{ setting('admin.gamification.tabs.streaks.ila', 'إلى') }} {{ $row['to'] }} @else {{ setting('admin.gamification.tabs.streaks.fma_fwq', 'فما فوق') }} @endif
            </span>
            <span class="font-bold">{{ $row['xp'] ?? 0 }} XP</span>
        </div>
    @empty
        <x-empty :message="setting('admin.gamification.tabs.streaks.alslm_fady_hml_alaftradyat_mn_zr_alreset', 'السلّم فاضي — حمّل الافتراضيّات من زرّ الـReset.')" />
    @endforelse
</section>

<section class="card p-4 md:p-5 mt-4">
    <h2 class="font-bold mb-1">{{ setting('admin.gamification.tabs.streaks.alstryk_wdrah', 'الستريك ودرعه') }}</h2>
    <ul class="text-sm space-y-1" style="color: var(--text-muted)">
        <li>• <strong>{{ setting('streaks.reward_days', 7) }}</strong> {{ setting('admin.gamification.tabs.streaks.ayam_mtwasla_zr_mkafaa_ymnh', 'أيّام متواصلة ⟵ زرّ مكافأة يمنح') }}
            <strong>{{ setting('streaks.reward_tickets', 1) }}</strong> {{ setting('admin.gamification.tabs.streaks.tdhkra_hdya_ila_sndwq_altdhakr', 'تذكرة هدية إلى صندوق التذاكر.') }}</li>
        <li>{{ setting('admin.gamification.tabs.streaks.tjmyd_alstryk', '• تجميد الستريك =') }} <strong>{{ (int) app(\App\Services\Gamification\StreakService::class)->freezeCost() }}</strong> {{ setting('admin.gamification.tabs.streaks.tdhkra_thmy_ywma_fayta_baqsa', 'تذكرة تحمي يومًا فايتًا، بأقصى') }} <strong>{{ setting('streaks.max_freezes_per_month', 2) }}</strong> {{ setting('admin.gamification.tabs.streaks.tjmydat_shhrya_wla_yhma_ywm_aqdm_mn', 'تجميدات شهريًّا، ولا يُحمى يوم أقدم من') }} <strong>{{ setting('streaks.freeze_max_age_days', 2) }}</strong> {{ setting('admin.gamification.tabs.streaks.ayam', 'أيّام.') }}</li>
        <li>{{ setting('admin.gamification.tabs.streaks.mstwa_alahtfal', '• مستوى الاحتفال:') }} <strong>{{ setting('streaks.celebration_tier', 2) }}</strong> {{ setting('admin.gamification.tabs.streaks.mtwst', '(متوسّط).') }}</li>
    </ul>
</section>

@can('xp_rules.edit')
    @include('admin.volunteer.partials.settings-card', [
        'title' => setting('admin.gamification.tabs.streaks.iadadat_alstryks_wnady_alkhamsa', 'إعدادات الستريكس ونادي الخامسة'),
        'rows' => $data['settings'],
        'action' => route('admin.gamification.settings.save'),
        'resetAction' => route('admin.gamification.reset'),
        'resetPayload' => ['group' => 'gamification_streaks'],
        'open' => true,
    ])
@endcan
