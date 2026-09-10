{{--
  10.0-أ (قاعدة نهائيّة ✅) يطلب **سبعة** كروت KPI كاملةً في «نظرة عامّة».
  وقاعدة 2.15-أ-3 العامّة («أربعة كحدٍّ أقصى») لا تُطبَّق هنا: بندٌ خاصّ بهذه
  الشاشة يُقدَّم عليها. والشبكة عمودان على الموبايل (2.15-ج: صفّ قابل
  للتمرير الأفقيّ أو شبكة 2×2 — لا أربعة مضغوطة) وأربعة من `md` فأعلى.
--}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    @foreach ($overview['kpis'] as $card)
        <x-kpi :label="$card['label']" :value="$card['value']" :icon="$card['icon']" />
    @endforeach
</div>

<section class="card p-4 mb-4">
    <h2 class="font-bold text-sm mb-3">{{ setting('account.profile.details.badges_title', 'الشارات') }}</h2>
    <p class="text-sm" style="color: var(--text-muted)">
        {{ $overview['badges_count'] }} {{ setting('account.profile.details.badges_suffix', 'شارة مفتوحة') }}
    </p>
</section>

{{-- ⭐ تقدّم التدريبات والمسارات: بطاقة لكلّ تدريبٍ أخذه ببار تقدّم ونسبة (10.0-أ) --}}
@if (($overview['progress'] ?? collect())->isNotEmpty())
    <section class="card p-4 mb-4">
        <h2 class="font-bold text-sm mb-3">{{ setting('account.profile.overview.progress_title', 'تقدّم التدريبات') }}</h2>
        <div class="grid md:grid-cols-2 gap-3">
            @foreach ($overview['progress'] as $row)
                <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span class="truncate">{{ $row['course']->name_ar }}</span>
                        <span class="shrink-0 tabular-nums" style="color: var(--text-muted)">{{ $row['percent'] }}%</span>
                    </div>
                    <div class="rounded-full overflow-hidden mt-2" style="background: var(--surface-raised); height: 6px">
                        <div class="h-full motion-standard" style="width: {{ $row['percent'] }}%; background: var(--color-brand-500)"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif

{{-- ⭐ خريطة حراريّة للحضور — نادي الخامسة، عرض السنة كتقويم (10.0-أ) --}}
@if (($overview['heatmap'] ?? []) !== [])
    <section class="card p-4 mb-4">
        <h2 class="font-bold text-sm mb-3">{{ setting('account.profile.overview.heatmap_title', 'خريطة الحضور') }}</h2>
        @include('achievements.components.heatmap', ['heatmap' => $overview['heatmap'], 'from' => $overview['heatmap_from'], 'to' => $overview['heatmap_to']])
    </section>
@endif

<div class="grid md:grid-cols-2 gap-3">
    <section class="card p-4">
        <h2 class="font-bold text-sm mb-3">{{ setting('account.profile.overview.general_title', 'بيانات عامّة') }}</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex items-center justify-between gap-2">
                <dt style="color: var(--text-muted)">{{ setting('account.profile.overview.joined_label', 'تاريخ الانضمام') }}</dt>
                <dd title="{{ $overview['joined_at']?->format('Y-m-d') }}">{{ $overview['joined_at']?->diffForHumans() }}</dd>
            </div>

            {{-- ⭐ المحافظة عامّة دائمًا ولا تخضع لإعداد الخصوصيّة (12.14-د) --}}
            <div class="flex items-center justify-between gap-2">
                <dt style="color: var(--text-muted)">{{ setting('account.profile.overview.governorate_label', 'المحافظة') }}</dt>
                <dd>{{ $overview['governorate'] ?? setting('account.profile.overview.missing_label', 'مش مضافة') }}</dd>
            </div>

            @if ($overview['can_see_country'])
                <div class="flex items-center justify-between gap-2">
                    <dt style="color: var(--text-muted)">{{ setting('account.profile.overview.country_label', 'الدولة') }}</dt>
                    <dd>{{ $overview['country'] ?? setting('account.profile.overview.missing_label', 'مش مضافة') }}</dd>
                </div>
            @endif
        </dl>
    </section>

    <section class="card p-4">
        <h2 class="font-bold text-sm mb-3">{{ setting('account.profile.overview.contact_title', 'التواصل') }}</h2>

        {{--
          الحسّاس مخفيّ افتراضيًّا (13.4-م): الحقل المقفول **لا يُعرَض فراغًا**،
          بل يُعرَض ما يشرح الحال في سطر واحد بلا أرقام ولا بريد.
        --}}
        <dl class="space-y-2 text-sm">
            <div class="flex items-center justify-between gap-2">
                <dt style="color: var(--text-muted)">{{ setting('account.profile.overview.phone_label', 'رقم الموبايل') }}</dt>
                <dd>
                    @if ($visibility->canSee('phone', $viewer, $owner, $level))
                        {{ $owner->phone ?? setting('account.profile.overview.missing_male_label', 'مش مضاف') }}
                    @else
                        <span style="color: var(--text-muted)">{{ setting('account.profile.overview.locked_label', 'مش متاح') }}</span>
                    @endif
                </dd>
            </div>
            <div class="flex items-center justify-between gap-2">
                <dt style="color: var(--text-muted)">{{ setting('account.profile.overview.email_label', 'البريد الإلكترونيّ') }}</dt>
                <dd>
                    @if ($visibility->canSee('email', $viewer, $owner, $level))
                        {{ $owner->email }}
                    @else
                        <span style="color: var(--text-muted)">{{ setting('account.profile.overview.locked_label', 'مش متاح') }}</span>
                    @endif
                </dd>
            </div>
        </dl>

        @if ($isOwner)
            <a href="{{ route('settings.privacy') }}" class="inline-block mt-3 text-xs underline"
               style="color: var(--color-brand-500)">{{ setting('account.profile.overview.privacy_link', 'اتحكّم في مين يشوف بياناتك') }}</a>
        @endif
    </section>
</div>
