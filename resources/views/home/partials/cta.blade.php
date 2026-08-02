@php
    /** دعوة التسجيل الأخيرة — فعل واحد بارز وبلا ضغط ولا عدّاد وهميّ (2.9 · 2.15-أ-2) */
    $title = (string) setting('home.cta.title', 'ابدأ من غير ما تدفع حاجة');
    $body = (string) setting('home.cta.body', 'أنشئ حسابك، فعّله مجّانًا، وابدأ أوّل تدريب النهارده.');
    $button = (string) setting('home.cta.button', 'أنشئ حسابك دلوقتي');
@endphp

<section class="card relative overflow-hidden p-6 md:p-8 mb-6 text-center" aria-labelledby="home-cta-title">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true"
         style="background-image: radial-gradient(rgb(0 212 184 / .10) 1px, transparent 1px); background-size: 32px 32px;"></div>

    <div class="relative">
        <h2 id="home-cta-title" class="text-xl md:text-2xl font-extrabold">{{ $title }}</h2>
        <p class="mt-2 text-sm" style="color: var(--text-muted)">{{ $body }}</p>

        <a href="{{ route('register') }}"
           class="btn inline-flex items-center justify-center gap-2 mt-5 rounded-xl px-6 py-3 text-sm font-bold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">
            {{ $button }}
            @include('home.partials.icon', ['name' => 'arrow', 'size' => 18])
        </a>

        <p class="mt-3 text-xs" style="color: var(--text-muted)">
            {{ setting('home.cta.note', 'عندك حساب بالفعل؟') }}
            <a href="{{ route('login') }}" class="underline" style="color: var(--color-brand-500)">{{ setting('home.nav.login', 'تسجيل الدخول') }}</a>
        </p>
    </div>
</section>
