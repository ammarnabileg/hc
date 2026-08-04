{{-- الاحتفالات (2.14): ربط كلّ حدث بمستواه · الصوت · نصّ التهنئة · الحدّ اليوميّ للذروة --}}

<div class="card p-3 mb-4 text-sm space-y-1">
    <div><x-icon name="lock" size="16" /> <strong>{{ setting('admin.gamification.tabs.celebrations.thlatha_mstwyat_la_raba', 'ثلاثة مستويات لا رابع') }}</strong> {{ setting('admin.gamification.tabs.celebrations.walhd_alywmy_lmstwa_aldhrwa', '— والحدّ اليوميّ لمستوى الذروة:') }}
        <strong>{{ setting('celebrations.peak.daily_cap', 3) }}</strong> {{ setting('admin.gamification.tabs.celebrations.ky_tbqa_aldhrwa_dhrwa', 'كي تبقى الذروة ذروةً.') }}</div>
    <div><x-icon name="xp" size="16" /> <strong>{{ setting('admin.gamification.tabs.celebrations.alanymyshn_hadr_dayma_bla_twjl', 'الأنيميشن حاضر دائمًا بلا توجل') }}</strong> {{ setting('admin.gamification.tabs.celebrations.walswt_whdh_ykhda_ltwjl_alswt_fy_albrwfayl', '— والصوت وحده يخضع لتوجل الصوت في البروفايل.') }}</div>
    <div><x-icon name="lock" size="16" /> {{ setting('admin.gamification.tabs.celebrations.mmnwa_alahtfal_bhdth_slby_aw_bshra_bla_injaz', 'ممنوع الاحتفال بحدث سلبيّ أو بشراء بلا إنجاز — ولا تتراكم: يُعرَض الأعلى مستوى فقط.') }}</div>
</div>

<section class="space-y-2">
    @forelse ($data['events'] as $event)
        <form method="post" action="{{ route('admin.gamification.celebrations.save', $event) }}"
              class="card p-3 grid md:grid-cols-4 gap-2 items-end">
            @csrf

            <div class="min-w-0">
                <div class="text-sm font-semibold truncate">{{ $event->label_ar }}</div>
                <code class="text-xs" style="color: var(--text-muted)">{{ $event->key }}</code>
            </div>

            <label class="text-xs">{{ setting('admin.gamification.tabs.celebrations.almstwa', 'المستوى') }}
                <select name="tier" class="w-full rounded-lg px-2 py-1.5 mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($data['tiers'] as $tier => $label)
                        <option value="{{ $tier }}" @selected($event->tier === $tier)>{{ $tier }} — {{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-xs">{{ setting('admin.gamification.tabs.celebrations.mlf_alswt_llswt_whdh_twjl', 'ملفّ الصوت (للصوت وحده توجل)') }}
                <input type="text" name="sound_path" value="{{ $event->sound_path }}"
                       class="w-full rounded-lg px-2 py-1.5 mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <label class="text-xs">{{ setting('admin.gamification.tabs.celebrations.ns_althnya', 'نصّ التهنئة') }}
                <input type="text" name="message_ar" value="{{ $event->message_ar }}" maxlength="500"
                       class="w-full rounded-lg px-2 py-1.5 mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <div class="md:col-span-4 flex items-center justify-between gap-2">
                <label class="text-xs flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" @checked($event->is_active)>
                    {{ setting('admin.gamification.tabs.celebrations.mfal', 'مفعَّل') }}
                </label>
                @can('celebrations.edit')
                    <button type="submit" class="btn rounded-xl px-3 py-1.5 text-xs font-semibold"
                            style="background: var(--surface-raised)">{{ setting('admin.gamification.tabs.celebrations.ahfz', 'احفظ') }}</button>
                @endcan
            </div>
        </form>
    @empty
        <x-empty :message="setting('admin.gamification.tabs.celebrations.kl_alahdath_ala_mstwyatha_alaftradya', 'كلّ الأحداث على مستوياتها الافتراضيّة.')" />
    @endforelse
</section>

@can('celebrations.edit')
    @include('admin.volunteer.partials.settings-card', [
        'title' => setting('admin.gamification.tabs.celebrations.iadadat_alahtfalat', 'إعدادات الاحتفالات'),
        'rows' => $data['settings'],
        'action' => route('admin.gamification.settings.save'),
        'resetAction' => route('admin.gamification.reset'),
        'resetPayload' => ['group' => 'gamification_celebrations'],
        'lockedKeys' => [
            'celebrations.animation_always_on',
            'celebrations.tiers_locked',
            'celebrations.once_per_event',
        ],
    ])
@endcan
