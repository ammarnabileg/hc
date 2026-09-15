@php
    // المسارات الخمسة وعتباتها من 10.1 — والمستويات مفتوحة بلا سقف بنفس المعادلة
    $canSee = $visibility->canSee('achievements', $viewer, $owner, $level);
@endphp

@if (! $canSee)
    <x-empty :message="setting('account.profile.achievements.hidden_message', 'الإنجازات مش متاحة على البروفايل ده.')" />
@else
    {{-- ⭐ بطاقة الإنجاز قابلة للاستخراج كصورة (12.14-هـ) --}}
    <div class="flex justify-end mb-3">
        <x-export-image
            kind="card"
            :title="$owner->shortName().' — '.setting('account.profile.achievements.export_title', 'إنجازاتي')"
            :subtitle="setting('account.profile.achievements.export_subtitle', 'مستوى الحساب').' '.$owner->level"
            :rows="collect($achievements)->values()->map(fn ($track, $i) => [
                'rank' => $track['level'],
                'u' => $owner->id,
                'name' => $track['label'],
                'value' => number_format($track['value']).' '.$track['unit'],
            ])->all()" />
    </div>

    @php
        // أيقونة كلّ مسار من مفهومه — حرفيًّا بأسلوب المرجع (chart/streak/referral/ticket/book)
        $trackIcons = ['account' => 'chart', 'club_5am' => 'streak', 'referrals' => 'users', 'tickets' => 'ticket', 'learning' => 'book'];
    @endphp

    {{-- صفّ إنجاز — حرفيًّا من ملف الهويّة (`.achievement-row`): أيقونة + عنوان/قيمة + تقدّم --}}
    <div>
        @foreach ($achievements as $track)
            <div class="achievement-row">
                <x-icon :name="$trackIcons[$track['key']] ?? 'chart'" size="24" />
                <div class="min-w-0">
                    <h3 class="truncate">{{ $track['label'] }}</h3>
                    <p class="small">{{ number_format($track['value']) }} {{ $track['unit'] }}</p>
                </div>
                <div>
                    <div class="spread small mb-2">
                        <span>{{ setting('account.profile.achievements.next_prefix', 'الجاي عند') }} {{ number_format($track['next_threshold']) }}</span>
                        <span>{{ (int) $track['percent'] }}%</span>
                    </div>
                    <div class="progress" role="progressbar" aria-valuenow="{{ (int) $track['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                        <span style="--value: {{ (int) $track['percent'] }}%"></span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
