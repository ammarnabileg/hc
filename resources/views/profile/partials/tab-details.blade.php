{{--
  تاب «تفاصيل»: بقيّة كروت 10.0-أ التي لا تسعها الأربعة الظاهرة في «نظرة عامّة».
  فالبند لم يُحذَف — هو خلف خطوة واحدة كما ينصّ 2.15-أ-3.
--}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    @foreach ($overview['kpis'] as $card)
        <x-kpi :label="$card['label']" :value="$card['value']" :icon="$card['icon']" />
    @endforeach
</div>

<section class="card p-4">
    <h2 class="font-bold text-sm mb-3">{{ setting('account.profile.details.badges_title', 'الشارات') }}</h2>
    <p class="text-sm" style="color: var(--text-muted)">
        {{ $overview['badges_count'] }} {{ setting('account.profile.details.badges_suffix', 'شارة مفتوحة') }}
    </p>
</section>
