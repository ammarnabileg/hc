@php
    /**
     * إجراءات صفّ الجدولة (24.3-خامسًا): تعديل · شغّل الآن · إيقاف · سجلّ · حذف.
     * وكلّها مخفيّة لمن لا يملكها (2.15-أ-7).
     */
    $u = auth()->user();
    $canEdit = $u?->can('report_schedules.edit');
    $canRun = $u?->can('report_schedules.manage');
    $canDelete = $u?->can('report_schedules.delete');
@endphp

<div class="flex flex-wrap items-center gap-3 text-xs">
    @if ($canEdit)
        <button type="button" class="underline" data-schedule-edit
                data-action="{{ route('admin.report-schedules.update', $schedule) }}"
                data-name="{{ $schedule->name }}"
                data-tab="{{ $schedule->report_tab }}"
                data-format="{{ $schedule->format }}"
                data-frequency="{{ $schedule->frequency }}"
                data-hour="{{ $schedule->hour }}"
                data-dow="{{ $schedule->day_of_week }}"
                data-dom="{{ $schedule->day_of_month }}"
                data-tz="{{ $schedule->timezone }}"
                data-period="{{ $schedule->period_days }}"
                data-emails="{{ implode(', ', $schedule->recipient_emails ?? []) }}"
                data-compare="{{ $schedule->include_comparison ? 1 : 0 }}"
                data-skip="{{ $schedule->skip_when_empty ? 1 : 0 }}">تعديل</button>
    @endif

    @if ($canRun)
        <form method="post" action="{{ route('admin.report-schedules.run', $schedule) }}">
            @csrf
            <button type="submit" class="underline">شغّل الآن</button>
        </form>
    @endif

    @if ($canEdit)
        <form method="post" action="{{ route('admin.report-schedules.toggle', $schedule) }}">
            @csrf
            <button type="submit" class="underline">{{ $schedule->isActive() ? 'أوقف' : 'فعّل' }}</button>
        </form>
    @endif

    <a class="underline" href="{{ route('admin.report-schedules.log', $schedule) }}">سجلّ الإرسال</a>

    @if ($canDelete)
        <form method="post" action="{{ route('admin.report-schedules.destroy', $schedule) }}"
              onsubmit="return confirm('تحذف الجدولة دي؟ سجلّ إرسالها هيتحذف معاها.')">
            @csrf @method('delete')
            <button type="submit" class="underline" style="color: var(--color-state-danger)">حذف</button>
        </form>
    @endif
</div>
