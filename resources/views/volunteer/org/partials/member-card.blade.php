@php
    /**
     * كارت عضو مضغوط (24.4-7): أفاتار بلا هالة · الاسم + شارة Rep · البوزشن ·
     * الفرعيّ · الأبلاين المباشر · شارات الغياب و«قائم بأعمال» · إطار ذهبيّ لنادي التميّز.
     * ⛔ وبلا أرقام أداء تفصيليّة لغير المخوَّل (مستوى «زميل» — 13.4-م).
     */
    $goldStyle = $card['is_club']
        ? 'border-color: var(--color-state-honor); box-shadow: inset 0 0 0 1px var(--color-state-honor)'
        : '';
@endphp

<button type="button"
        class="card p-3 text-start w-full motion-standard hover:opacity-95 animate-fadeup"
        style="{{ $goldStyle }}"
        data-member="{{ route('volunteer.department.member', $card['id']) }}"
        data-member-name="{{ $card['name'] }}">
    <div class="flex items-start gap-3">
        <x-avatar :user="$card['user']" size="11" />

        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-bold text-sm truncate">{{ $card['short_name'] }}</span>
                <x-state-badge :state="$card['rep_state']" :label="$card['rep_label']" />
                @if ($card['is_club'])
                    <span class="text-xs" style="color: var(--color-state-honor)" title="نادي التميّز">★</span>
                @endif
            </div>

            <div class="text-xs mt-1" style="color: var(--text-muted)">
                {{ $card['position'] }} · {{ $card['entity'] }}
            </div>

            @if ($card['upline'])
                <div class="text-xs mt-0.5" style="color: var(--text-muted)">
                    الأبلاين: {{ $card['upline'] }}
                </div>
            @endif

            <div class="flex flex-wrap gap-1 mt-2">
                @if ($card['absent_until'])
                    <x-state-badge
                        state="idle"
                        :label="'غائب حتى '.$card['absent_until']->translatedFormat(setting('volunteer.org.date_format', 'j F')).($card['delegate'] ? ' — البديل: '.$card['delegate'] : '')" />
                @endif

                @if ($card['is_acting'])
                    <x-state-badge state="warn" label="قائم بأعمال" />
                @endif

                @if ($card['status'] === 'suspended')
                    <x-state-badge state="idle" label="معلَّق" />
                @endif

                {{-- الأرقام التشغيليّة للمخوَّل وحده — تُخفى ولا تُعطَّل (2.15-أ-7) --}}
                @can('team_health.view')
                    @if ($card['load'] > 0)
                        <span class="text-xs rounded-full px-2 py-0.5"
                              style="background: var(--surface-sunken); color: var(--text-muted)">
                            تحته {{ $card['load'] }}
                        </span>
                    @endif
                @endcan
            </div>
        </div>
    </div>
</button>
