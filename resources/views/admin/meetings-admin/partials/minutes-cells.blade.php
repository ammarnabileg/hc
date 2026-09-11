@php
    /**
     * عمودا **«المحضر والمرفقات»** و**«التسجيل»** من جدول 24.2-أوّلًا.
     *
     * ولماذا حالةٌ لا مجرّد رابط؟ لأنّ السؤال التشغيليّ هو «هل الاجتماع
     * موثَّق؟» — فالصفر يجب أن يُقرَأ من مسافة، ورمز الحالة يحمل المعنى مع
     * اللون لا اللون وحده (2.16-ب).
     *
     * و«التسجيل» ثلاث حالات لا اثنتان: تسجيلٌ موجود · اجتماعٌ أونلاين بلا
     * تسجيل · اجتماعٌ حضوريّ لا يُنتظَر له تسجيل أصلًا — وخلطُ الأخيرتين
     * يصنع تنبيهًا كاذبًا في كلّ صفٍّ حضوريّ.
     */
    $hasMinutes = filled($meeting->minutes);
    $isOnline = filled($meeting->external_link);
@endphp

<td class="px-4 py-3 text-xs">
    <a href="{{ route('admin.meetings.show', $meeting) }}" class="inline-flex items-center gap-2">
        <x-state-badge :state="$hasMinutes ? 'ok' : ($meeting->status === 'ended' ? 'warn' : 'idle')"
                       :label="$hasMinutes ? setting('admin.meetings_admin.partials.minutes_cells.mhdr_mrfwa', 'محضر مرفوع') : setting('admin.meetings_admin.partials.minutes_cells.bla_mhdr', 'بلا محضر')" />
        <span style="color: var(--text-muted)">{{ $attachmentCount }} {{ setting('admin.meetings_admin.partials.minutes_cells.mrfq', 'مرفقًا') }}</span>
    </a>
</td>

<td class="px-4 py-3 text-xs">
    @if (filled($meeting->recording_url))
        <a class="underline" href="{{ $meeting->recording_url }}" rel="noopener" target="_blank">{{ setting('admin.meetings_admin.partials.minutes_cells.fth_altsjyl', 'افتح التسجيل') }}</a>
    @elseif ($isOnline)
        <span style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.minutes_cells.bla_tsjyl', 'بلا تسجيل') }}</span>
    @else
        <span style="color: var(--text-muted)">{{ setting('admin.meetings_admin.partials.minutes_cells.hdwry', 'حضوريّ') }}</span>
    @endif
</td>
