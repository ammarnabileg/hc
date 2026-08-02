{{-- نظرة عامّة: أربعة كروت KPI بحدّ أقصى في صفّ واحد (2.15-أ-3) --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <x-kpi label="مستوى الحساب" :value="$overview['level']" icon="🎯" />
    <x-kpi label="نقاط الخبرة" :value="$overview['xp']" icon="⚡" />
    <x-kpi label="الشهادات" :value="$overview['certificates_count']" icon="🎓" />
    <x-kpi label="الشارات" :value="$overview['badges_count']" icon="🏅" />
</div>

<div class="grid md:grid-cols-2 gap-3">
    <section class="card p-4">
        <h2 class="font-bold text-sm mb-3">بيانات عامّة</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex items-center justify-between gap-2">
                <dt style="color: var(--text-muted)">تاريخ الانضمام</dt>
                <dd title="{{ $overview['joined_at']?->format('Y-m-d') }}">{{ $overview['joined_at']?->diffForHumans() }}</dd>
            </div>

            {{-- ⭐ المحافظة عامّة دائمًا ولا تخضع لإعداد الخصوصيّة (12.14-د) --}}
            <div class="flex items-center justify-between gap-2">
                <dt style="color: var(--text-muted)">المحافظة</dt>
                <dd>{{ $overview['governorate'] ?? 'مش مضافة' }}</dd>
            </div>

            @if ($overview['can_see_country'])
                <div class="flex items-center justify-between gap-2">
                    <dt style="color: var(--text-muted)">الدولة</dt>
                    <dd>{{ $overview['country'] ?? 'مش مضافة' }}</dd>
                </div>
            @endif
        </dl>
    </section>

    <section class="card p-4">
        <h2 class="font-bold text-sm mb-3">التواصل</h2>

        {{--
          الحسّاس مخفيّ افتراضيًّا (13.4-م): الحقل المقفول **لا يُعرَض فراغًا**،
          بل يُعرَض ما يشرح الحال في سطر واحد بلا أرقام ولا بريد.
        --}}
        <dl class="space-y-2 text-sm">
            <div class="flex items-center justify-between gap-2">
                <dt style="color: var(--text-muted)">رقم الموبايل</dt>
                <dd>
                    @if ($visibility->canSee('phone', $viewer, $owner, $level))
                        {{ $owner->phone ?? 'مش مضاف' }}
                    @else
                        <span style="color: var(--text-muted)">مش متاح</span>
                    @endif
                </dd>
            </div>
            <div class="flex items-center justify-between gap-2">
                <dt style="color: var(--text-muted)">البريد الإلكترونيّ</dt>
                <dd>
                    @if ($visibility->canSee('email', $viewer, $owner, $level))
                        {{ $owner->email }}
                    @else
                        <span style="color: var(--text-muted)">مش متاح</span>
                    @endif
                </dd>
            </div>
        </dl>

        @if ($isOwner)
            <a href="{{ route('settings.privacy') }}" class="inline-block mt-3 text-xs underline"
               style="color: var(--color-brand-500)">اتحكّم في مين يشوف بياناتك</a>
        @endif
    </section>
</div>
