@php
    /**
     * شارة درجة الالتزام (Rep) — ثابتة بجانب كلّ اسم في كلّ صفحات لوحة التطوّع (13.4-ح).
     *
     * الاستعمال من أيّ مجال:
     *   @include('volunteer.components.rep-badge', ['user' => $member])
     *   @include('volunteer.components.rep-badge', ['score' => 4.25, 'name' => 'اسم العضو'])
     *
     * العتبات من جدول Rep الموحَّد (13.4-ن) — ولا رقم محروق:
     *   موجب ⟵ أخضر · تحت الصفر وفوق عتبة المؤشّر الأحمر ⟵ أصفر · تحت −8 ⟵ أحمر.
     * ومع كلّ لون رمزٌ دائمًا لأنّ اللون وحده لا يحمل المعنى (2.16-ب).
     */
    $repUser = $user ?? null;
    $repValue = $score ?? ($repUser?->repScore?->score);
    $repName = $name ?? null;
    $repHasValue = $repValue !== null;
    $repValue = (float) ($repValue ?? 0);

    $repRed = rep_rule('limit.red_indicator', -8);
    $repWarn = rep_rule('limit.warning_threshold', -5);
    $repClub = rep_rule('limit.club_threshold', 9.5);

    $repState = match (true) {
        ! $repHasValue => 'idle',
        $repValue <= $repRed => 'danger',
        $repValue < 0 => 'warn',
        $repValue >= $repClub => 'honor',
        default => 'ok',
    };

    $repSymbols = state_color($repState);

    $repHint = match ($repState) {
        'idle' => 'لسّه مفيش درجة التزام مسجّلة',
        'danger' => 'المؤشّر الأحمر: الدرجة تحت '.rtrim(rtrim(number_format($repRed, 2), '0'), '.').' — راجع معاملاتك واتكلّم مع مسؤولك',
        'warn' => 'الدرجة تحت الصفر — فيه مجال تعوّضها قبل عتبة الإنذار ('.rtrim(rtrim(number_format($repWarn, 2), '0'), '.').')',
        'honor' => 'نادي التميّز: '.rtrim(rtrim(number_format($repClub, 2), '0'), '.').' فأعلى',
        default => 'درجة التزام سليمة',
    };

    $repDisplay = $repHasValue ? rtrim(rtrim(number_format($repValue, 2), '0'), '.') : '—';
@endphp

<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs align-middle cursor-help"
      style="background: color-mix(in srgb, var(--color-state-{{ $repSymbols['color'] }}) 15%, transparent);
             color: var(--color-state-{{ $repSymbols['color'] }})"
      title="درجة الالتزام {{ $repDisplay }} — {{ $repHint }}"
      aria-label="درجة الالتزام {{ $repDisplay }}. {{ $repHint }}">
    <span aria-hidden="true">{{ $repSymbols['icon'] }}</span>
    <span>{{ $repDisplay }}</span>
    @if ($repName)
        <span class="opacity-80">{{ $repName }}</span>
    @endif
</span>
