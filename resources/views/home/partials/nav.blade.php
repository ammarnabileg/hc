@php
    /** شريط علويّ للزائر: الاسم + فعل رئيسيّ واحد بارز (2.15-أ-2) */
    $brand = (string) setting('home.brand.name', config('app.name'));
@endphp

<header class="flex items-center justify-between gap-3 flex-wrap mb-6">
    <a href="{{ route('home') }}" class="flex items-center gap-2 font-extrabold text-lg">
        <span class="inline-flex items-center justify-center rounded-xl"
              style="width:38px;height:38px;background: color-mix(in srgb, var(--color-brand-500) 15%, transparent); color: var(--color-brand-500)">
            @include('home.partials.icon', ['name' => 'spark', 'size' => 22])
        </span>
        <span>{{ $brand }}</span>
    </a>

    <nav class="flex items-center gap-2" aria-label="{{ setting('home.nav.aria_label_1', 'روابط الحساب') }}">
        <a href="{{ route('login') }}"
           class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm motion-standard"
           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            {{ setting('home.nav.login', 'تسجيل الدخول') }}
        </a>
        <a href="{{ route('register') }}"
           class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-bold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">
            {{ setting('home.nav.register', 'أنشئ حسابك') }}
        </a>
    </nav>
</header>
