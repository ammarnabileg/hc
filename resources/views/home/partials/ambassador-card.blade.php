@php
    /**
     * ⭐ بطاقة العضو السفير — نفس شكل بطاقة العضو (13.4-ر) مع **اللقب** ظاهرًا.
     * ولقب السفير **ليس شارة** (7.6.1) فلا يُرسَم كشارة إنجاز، بل كحبّة لقب ذهبيّة
     * هادئة — شرف لا حالة تشغيليّة (2.16).
     * الاستعمال في أيّ مكان آخر (بروفايل/بطاقة):
     *   @include('home.partials.ambassador-card', ['ambassador' => $user])
     */
    $rank = $rank ?? null;
    $invites = (int) ($ambassador->ambassador_invites ?? 0);
@endphp

<article class="card p-4 flex items-center gap-3">
    @if ($rank)
        <span class="shrink-0 inline-flex items-center justify-center rounded-xl text-sm font-extrabold"
              style="width:34px;height:34px;background: var(--surface-sunken); color: var(--text-muted)">{{ $rank }}</span>
    @endif

    <x-avatar :user="$ambassador" size="12" />

    <div class="min-w-0 flex-1">
        <a href="{{ url('/u/'.$ambassador->code) }}" class="font-bold text-sm truncate block hover:underline">
            {{ $ambassador->shortName() }}
        </a>

        {{-- اللقب — ذهبيّ الشرف ومعه رمز دائمًا (2.16) --}}
        @if ($ambassador->ambassador_title)
            <span class="inline-flex items-center gap-1 mt-1 rounded-full px-2 py-0.5 text-xs font-bold"
                  style="background: color-mix(in srgb, var(--color-state-honor) 16%, transparent); color: var(--color-state-honor)">
                <span aria-hidden="true">★</span>{{ $ambassador->ambassador_title }}
            </span>
        @endif
    </div>

    <div class="text-center shrink-0">
        {{-- عدّاد تصاعديّ — والرقم النهائيّ يظهر في كلّ الأحوال (2.17-أ) --}}
        <div class="text-lg font-extrabold" data-count-to="{{ $invites }}">{{ $invites }}</div>
        <div class="text-xs" style="color: var(--text-muted)">{{ setting('ambassadors.invites_label', 'دعوة مفعّلة') }}</div>
    </div>
</article>
