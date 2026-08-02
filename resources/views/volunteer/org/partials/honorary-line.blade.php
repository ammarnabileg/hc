@php
    /**
     * العنصر الشرفيّ «أخوكم» (13.4-ص-ب): سطر شرفيّ في رأس الصفحة —
     * لا ضمن عدّاد الأعضاء ولا ضمن الفلاتر، وبلا أيّ مؤشّر تشغيليّ.
     * والذهبيّ لونُ شرفٍ لا حالةٍ تشغيليّة (2.16-أ).
     */
@endphp

@if ($honorary)
    <div class="card p-3 mb-4 flex items-center gap-3"
         style="border-color: var(--color-state-honor); background:
                color-mix(in srgb, var(--color-state-honor) 6%, var(--surface-raised))">
        <x-avatar :user="$honorary['user']" size="9" />
        <div class="min-w-0">
            <div class="font-bold text-sm flex items-center gap-2">
                {{ $honorary['user']->shortName() }}
                <x-state-badge state="honor" :label="$honorary['label']" />
            </div>
            <div class="text-xs" style="color: var(--text-muted)">
                {{ setting('volunteer.honorary.note', 'عنصر شرفيّ — بلا مؤشّرات ولا يدخل أيّ عدّاد') }}
            </div>
        </div>
    </div>
@endif
