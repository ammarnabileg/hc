{{--
  كارت النتيجة (13.1): أفاتار بأحرف الاسم · الاسم · #الكود · الدولة/المحافظة ·
  حالة «فعّال» · [عرض] — ⭐ وبلا أيّ بيان حسّاس (لا موبايل ولا بريد).
--}}
@foreach ($results as $person)
    <article class="card p-4 flex items-center gap-3 animate-fadeup">
        <x-avatar :name="$person->name" size="11" />

        <div class="min-w-0 flex-1">
            <h2 class="font-semibold text-sm truncate">{{ $person->name }}</h2>
            <div class="text-xs font-mono" style="color: var(--text-muted)">#{{ $person->code }}</div>
            <div class="text-xs mt-1" style="color: var(--text-muted)">
                {{-- ⭐ المحافظة حقل عامّ دائمًا (12.14-د) --}}
                {{ collect([$person->country?->name_ar, $person->governorate?->name_ar])->filter()->implode(' · ') ?: 'مش مضافة' }}
            </div>
        </div>

        <div class="flex flex-col items-end gap-2 shrink-0">
            <x-state-badge :state="$person->status === 'active' ? 'ok' : 'idle'"
                           :label="$person->status === 'active' ? 'فعّال' : 'غير فعّال'" />

            <a href="{{ route('u.profile', ['code' => $person->code]) }}"
               class="btn rounded-xl px-3 py-1.5 text-xs font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">عرض</a>
        </div>
    </article>
@endforeach
