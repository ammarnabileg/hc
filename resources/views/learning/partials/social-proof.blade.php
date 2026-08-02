@php
    /**
     * الدليل الاجتماعيّ الحيّ على صفحة التدريب (3.4-45 · 3.4-49).
     *
     * 🛡️ **أرقام حقيقيّة لا مجمَّلة** (2.9): العدّاد يُقرأ من الجداول نفسها، وتحت
     * الحدّ الأدنى (2.9-7) لا نضخّمه بل **نغيّر التأطير إلى الريادة** — «كن أوّل
     * من ينهي هذا التدريب». نقول الصدق دائمًا ونختار أيّ وجهٍ صادقٍ نُبرزه.
     *
     * $social = ['learners' => frame(...), 'joined' => frame(...)]
     */
@endphp

@if ($social['learners']['text'] !== '' || $social['joined']['text'] !== '')
    <div class="flex flex-wrap items-center gap-2 mb-4 text-xs">
        @if ($social['learners']['text'] !== '')
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1"
                  style="background: var(--surface-sunken); color: var(--text-muted)">
                <x-icon name="people" size="14" />
                <span>{{ $social['learners']['text'] }}</span>
            </span>
        @endif

        @if ($social['joined']['text'] !== '')
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1"
                  style="background: color-mix(in srgb, var(--color-brand-500) 10%, transparent); color: var(--color-brand-500)">
                <x-icon name="trophy" size="14" />
                <span>{{ $social['joined']['text'] }}</span>
            </span>
        @endif
    </div>
@endif
