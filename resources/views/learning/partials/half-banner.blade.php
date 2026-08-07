@php
    /**
     * ⭐ لافتة التهنئة عند نصّ التدريب (3.4-19).
     *
     * لماذا لافتة لا احتفال؟ لأنّ الاحتفال يحجب الشاشة ويستهلك حصّة الذروة
     * اليوميّة (2.14)، ونصف الطريق **محطّة تشجيع** لا لحظة ذروة: يستحقّ سطرًا
     * دافئًا يراه المتدرّب وهو يكمل، لا ستارًا يوقفه.
     *
     * والقرار كلّه في الخادم (`ProgressService::halfPoint`): اللافتة تظهر عند
     * الدرس الذي عبر النصّ بالضبط ثمّ تختفي — فلا تتحوّل إلى ضجيجٍ دائم.
     *
     * $half = ['reached' => bool, 'at' => int, 'remaining' => int]
     */
@endphp

@if (! empty($half['reached']) && setting('learning.ux.half_banner_enabled', true))
    <div role="status" aria-live="polite"
         class="card p-3 mb-4 flex items-center gap-3 flex-wrap animate-fadeup"
         style="border-inline-start: 3px solid var(--color-state-honor)">
        <span aria-hidden="true" style="color: var(--color-state-honor)">
            <x-icon name="celebrate" size="22" />
        </span>

        <div class="min-w-40 flex-1">
            <p class="text-sm font-bold">
                {{ str_replace(':name', auth()->user()->shortName(1), (string) setting('learning.course.half_banner_title', 'نصّ الطريق خلص يا :name — أحسنت!')) }}
            </p>
            {{-- الرقم الحقيقيّ لا تقدير: كم درسًا باقيًا فعلًا (2.9) --}}
            <p class="text-xs mt-0.5" style="color: var(--text-muted)">
                {{ str_replace(':count', (string) $half['remaining'], (string) setting('learning.course.half_banner_hint', 'باقي :count درس وتخلّص التدريب — كمّل وأنت في أقوى لحظاتك.')) }}
            </p>
        </div>
    </div>
@endif
