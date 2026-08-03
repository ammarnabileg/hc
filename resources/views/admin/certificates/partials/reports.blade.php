{{--
    5) صفحة التحقّق — **جدول البلاغات** (12.5-هـ · 24.1).

    النصّ الحاكم (24.1 — شاشة «صفحة التحقّق»): «وأسفلها **جدول البلاغات:** الكود ·
    المبلِّغ · السبب · التاريخ · الحالة · [مراجعة]» و«`pop-box` «مراجعة بلاغ»
    [التفاصيل + إجراء: تجاهل/إلغاء الشهادة/تصعيد]» و«**الحالات:** فارغة (لا بلاغات)».

    وكلّ نصٍّ هنا **إعدادٌ** لا حرفٌ محروق (2.13)، و**بلا تمرير أفقيّ على 375px**
    (2.15-ج): الصفوف كروتٌ تتراصّ لا جدولٌ يتمدّد.
--}}
<x-filters :action="route('admin.certificates.index')">
    <input type="hidden" name="tab" value="verification">

    <label class="block flex-1 min-w-[12rem]">
        <span class="block text-sm mb-1">{{ setting('certificates.reports.search_label', 'بحث') }}</span>
        <input type="search" name="q" value="{{ $reportFilters['q'] }}"
               placeholder="{{ setting('certificates.reports.search_placeholder', 'كود الشهادة أو نصّ البلاغ…') }}"
               class="w-full rounded-xl px-3 py-2 text-sm"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
    </label>

    <label class="block">
        <span class="block text-sm mb-1">{{ setting('certificates.reports.status_label', 'الحالة') }}</span>
        <select name="status" class="rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <option value="">{{ setting('certificates.reports.all_label', 'الكلّ') }}</option>
            @foreach ($reportStatuses as $key => $label)
                <option value="{{ $key }}" @selected($reportFilters['status'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">
        {{ setting('certificates.reports.filter_button', 'تصفية') }}
    </button>
</x-filters>

@if ($newReportsCount > 0)
    <p class="text-sm mb-3" style="color: var(--text-muted)">
        {{-- حتّى **رمز الاستبدال** إعدادٌ لا حرفٌ محروق (2.13): المالك يغيّر النصّ ورمزَه معًا --}}
        {{ str_replace(
            (string) setting('certificates.reports.count_placeholder', '[العدد]'),
            (string) $newReportsCount,
            (string) setting('certificates.reports.pending_line', 'فيه [العدد] بلاغًا لسّه ما اتراجعش.'),
        ) }}
    </p>
@endif

@if ($reports->isEmpty())
    <x-empty :message="setting('certificates.reports.empty', 'مفيش بلاغات — وده خبر كويّس.')" />
@else
    <div class="space-y-3">
        @foreach ($reports as $report)
            <div class="card p-4">
                <div class="flex items-start gap-3 flex-wrap">
                    <div class="flex-1 min-w-0">
                        {{-- الكود --}}
                        <div class="font-semibold break-words">
                            @if ($report->certificate)
                                <a href="{{ route('verify.certificate', ['code' => $report->code]) }}"
                                   class="underline">{{ $report->code }}</a>
                            @else
                                <span>{{ $report->code }}</span>
                                <span class="text-xs" style="color: var(--color-state-danger)">
                                    · {{ setting('certificates.reports.unknown_code', 'كود بلا شهادة في سجلّنا') }}
                                </span>
                            @endif
                        </div>

                        {{-- المبلِّغ · التاريخ --}}
                        <div class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ setting('certificates.reports.reporter_label', 'المبلِّغ') }}:
                            {{ $report->reporter?->name
                                ?: ($report->reporter_contact
                                    ?: setting('certificates.reports.anonymous', 'بلا حساب')) }}
                            · <span title="{{ $report->created_at }}">{{ $report->created_at?->diffForHumans() }}</span>
                        </div>

                        {{-- السبب --}}
                        <p class="text-sm mt-2 break-words">{{ $report->reason }}</p>

                        @if ($report->reviewed_at)
                            <div class="text-xs mt-2" style="color: var(--text-muted)">
                                {{ setting('certificates.reports.reviewed_by_label', 'راجعه') }}:
                                {{ $report->reviewer?->name ?: '—' }}
                                · {{ $report->reviewed_at->diffForHumans() }}
                                @if ($report->review_note)
                                    · {{ $report->review_note }}
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- الحالة --}}
                    <x-state-badge
                        :state="match ($report->status) { 'new' => 'warn', 'dismissed' => 'idle', 'escalated' => 'info', default => 'danger' }"
                        :label="$reportStatuses[$report->status] ?? $report->status" />
                </div>

                @if ($report->status === 'new')
                    <div class="mt-3 text-xs">
                        <button type="button" data-modal-open="report-review-{{ $report->id }}" class="underline">
                            {{ setting('certificates.reports.review_button', 'مراجعة') }}
                        </button>
                    </div>
                @endif
            </div>

            @if ($report->status === 'new')
                <x-modal :id="'report-review-'.$report->id"
                         :title="setting('certificates.reports.review_title', 'مراجعة بلاغ')">
                    <form method="post" action="{{ route('admin.certificates.reports.review', $report) }}" class="space-y-3">
                        @csrf

                        {{-- التفاصيل --}}
                        <div class="rounded-xl p-3 text-sm" style="background: var(--surface-sunken)">
                            <div class="font-semibold break-words">{{ $report->code }}</div>
                            <p class="mt-1 break-words">{{ $report->reason }}</p>
                            @if ($report->reporter_contact)
                                <p class="text-xs mt-1 break-words" style="color: var(--text-muted)">
                                    {{ setting('certificates.reports.contact_label', 'وسيلة تواصل') }}:
                                    {{ $report->reporter_contact }}
                                </p>
                            @endif
                        </div>

                        {{-- الإجراء: تجاهل / إلغاء الشهادة / تصعيد --}}
                        <fieldset class="space-y-2">
                            <legend class="text-sm mb-1">{{ setting('certificates.reports.action_label', 'الإجراء') }}</legend>
                            @foreach ($reportActions as $key => $label)
                                @if ($key !== 'revoked' || $canRevoke)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="radio" name="action" value="{{ $key }}" required>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endif
                            @endforeach
                        </fieldset>

                        @if ($canRevoke)
                            <label class="block">
                                <span class="block text-sm mb-1">
                                    {{ setting('certificates.reports.revoke_reason_label', 'سبب الإلغاء (لو اخترت الإلغاء)') }}
                                </span>
                                <select name="reason" class="w-full rounded-xl px-3 py-2 text-sm"
                                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    @foreach ($revokeReasons as $reason)
                                        <option value="{{ $reason }}">{{ $reason }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif

                        <label class="block">
                            <span class="block text-sm mb-1">{{ setting('certificates.reports.note_label', 'ملاحظة المراجعة (اختياريّة)') }}</span>
                            <textarea name="note" rows="2" class="w-full rounded-xl px-3 py-2 text-sm"
                                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                        </label>

                        <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                                style="background: var(--color-brand-500); color: #04201c">
                            {{ setting('certificates.reports.review_submit', 'سجّل المراجعة') }}
                        </button>
                    </form>
                </x-modal>
            @endif
        @endforeach
    </div>

    <div class="mt-4">{{ $reports->links() }}</div>
@endif
