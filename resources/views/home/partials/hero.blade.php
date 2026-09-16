@php
    /**
     * البطل (Hero) — سؤال واحد للشاشة: «إيه المنصّة دي وليه أسجّل؟» (2.15-أ-1).
     * كلّ نصّ فيها من `setting()` فالأدمن يعدّله بلا كود (2.13).
     *
     * ⛔ شبكة النقاط الخلفيّة (كانت هنا) مُلغاةٌ من الهويّة 2.0 نصًّا: «قسم
     * الزخارف مُلغًى بالكامل — الضوضاء وشبكتا النقاط/الخطوط والزجاج أُلغيت
     * نهائيًّا» (2.10.1-4)، وكانت أصلًا بلون الهويّة القديمة التركوازيّ
     * الميّت (`rgb(0 212 184)`) لا الأحمر الحاليّ — فائتةٌ من كلّ موجات
     * التطبيق السابقة لأنّها على صفحة الهبوط العامّة لا داخل المنصّة (§25 v5.9).
     */
    $eyebrow = (string) setting('home.hero.eyebrow', 'منصّة تعلّم وتطوّع عربيّة');
    $title = (string) setting('home.hero.title', 'اتعلّم مهارة حقيقيّة، واطلع بشهادة تقدر تثبتها.');
    $subtitle = (string) setting('home.hero.subtitle', 'تدريبات عربيّة مرتّبة في مسارات، ومجتمع بيشتغل جنبك، وشهادة لكلّ إنجاز، كلّه في مكان واحد.');
    $primary = (string) setting('home.hero.primary_cta', 'ابدأ مجّانًا');
    $secondary = (string) setting('home.hero.secondary_cta', 'اتفرّج على التدريبات');
@endphp

<section class="card p-6 md:p-10 mb-6" aria-labelledby="home-hero-title">
    <div class="max-w-3xl">
        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold"
              style="background: color-mix(in srgb, var(--color-brand-500) 14%, transparent); color: var(--color-brand-500)">
            @include('home.partials.icon', ['name' => 'spark', 'size' => 14])
            {{ $eyebrow }}
        </span>

        <h1 id="home-hero-title" class="mt-4 text-2xl md:text-4xl font-extrabold leading-snug">{{ $title }}</h1>
        <p class="mt-3 text-sm md:text-base" style="color: var(--text-muted)">{{ $subtitle }}</p>

        <div class="mt-6 flex flex-wrap gap-2">
            <a href="{{ route('register') }}"
               class="btn inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-bold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">
                {{ $primary }}
                @include('home.partials.icon', ['name' => 'arrow', 'size' => 18])
            </a>
            <a href="#home-learning"
               class="btn inline-flex items-center rounded-xl px-5 py-3 text-sm motion-standard"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $secondary }}</a>
        </div>
    </div>
</section>
