@extends('layouts.app')

@section('title', $title)
@section('meta_description', $subtitle)

@section('content')
    {{--
      ⭐ **صفحة سياسة الخصوصيّة** (21.3-د) — عامّة تمامًا: بلا تسجيل دخول ولا
      صلاحيّة (`routes/parts/public-pages.php` بلا `Route::middleware('auth')`).
      كلّ نصٍّ هنا من `setting()` (2.13) — والرابط لإعدادات الخصوصيّة يظهر
      لصاحب الحساب فقط؛ الزائر يقرأ بدله سطرًا يشرح مكان اختياره.
    --}}
    <x-page-header :title="$title" :subtitle="$subtitle" />

    <div class="grid gap-4 max-w-3xl">
        <section class="card p-4">
            <h2 class="font-bold text-sm">{{ $collectedTitle }}</h2>
            <p class="text-sm mt-2" style="color: var(--text-muted)">{{ $collectedBody }}</p>
        </section>

        <section class="card p-4">
            <h2 class="font-bold text-sm">{{ $sharedTitle }}</h2>
            <p class="text-sm mt-2" style="color: var(--text-muted)">{{ $sharedBody }}</p>
        </section>

        <section class="card p-4">
            <h2 class="font-bold text-sm">{{ $rightsTitle }}</h2>
            <p class="text-sm mt-2" style="color: var(--text-muted)">{{ $rightsBody }}</p>
        </section>

        {{--
          حقّ السحب في أيّ وقت (21.3-د · 24.5): لصاحب الحساب رابطٌ مباشر
          لإعداداته، وللزائر شرحٌ أنّ اختياره على المتصفّح وقابلٌ للتغيير من
          البانر أو بتسجيل الدخول لاحقًا.
        --}}
        <section class="card p-4">
            <h2 class="font-bold text-sm">{{ $withdrawTitle }}</h2>

            @auth
                <a href="{{ route('settings.privacy') }}"
                   class="btn inline-block mt-2 rounded-xl px-4 py-2 text-sm font-semibold"
                   style="background: var(--color-brand-500); color:#04201c">
                    {{ $withdrawCtaLabel }}
                </a>
            @else
                <p class="text-sm mt-2" style="color: var(--text-muted)">{{ $withdrawGuestNote }}</p>
            @endauth
        </section>
    </div>
@endsection
