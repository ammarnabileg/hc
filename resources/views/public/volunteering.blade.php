@extends('layouts.app')
@section('title', $heroTitle)
@section('meta_description', $heroBody)

@section('content')
    <x-page-header :title="$heroTitle" :subtitle="$heroBody" />

    @if ($excluded)
        {{-- بعد الإقصاء: العودة بقرار مشرف عام التطوّع (13.4-ق-و) --}}
        <x-empty
            message="العودة بعد الاستبعاد بتحتاج قرارًا من مشرف عام التطوّع. تواصل معنا من صفحة الدعم وهنشوف الموضوع."
            action="صفحة الدعم"
            :href="\Illuminate\Support\Facades\Route::has('complaints.index') ? route('complaints.index') : '#'" />
    @elseif ($inCooldown)
        {{-- داخل التبريد: الزرّ لا يُفتَح أصلًا ويظهر مكانه موعد الإتاحة --}}
        <div class="card p-6 text-center">
            <p class="text-sm">أهلًا بعودتك 👋 مكانك محفوظ عندنا.</p>
            <p class="mt-2 font-semibold">تقدر تبدأ من جديد يوم {{ $coolingUntil->translatedFormat('j F Y') }}.</p>
        </div>
    @else
        @if ($returning)
            <div class="card p-4 mb-4">
                <p class="text-sm">
                    أهلًا بعودتك 👋 <strong>تقدّمك القديم محفوظ</strong> — كمّل من حيث وقفت.
                    وعشان تدخل قائمة الانتظار الحاليّة، <strong>لازم تدخل الامتحان من جديد</strong>.
                </p>
            </div>
        @endif

        <div class="card p-6 text-center mb-6">
            {{-- فعل رئيسيّ واحد بارز (2.15-أ-2) --}}
            <a href="{{ \Illuminate\Support\Facades\Route::has('learning.paths') ? route('learning.paths') : '#' }}"
               class="btn inline-flex items-center justify-center rounded-xl px-6 py-3 font-semibold motion-standard"
               style="background: var(--color-brand-500); color:#04201c">{{ $ctaLabel }}</a>
        </div>
    @endif

    {{-- كلّ المحتوى يُدار من لوحة الإدارة: إضافة وتعديل وحذف (13.4-أ) --}}
    @forelse ($sections as $section)
        <section class="card p-5 mb-4">
            <h2 class="font-bold mb-2">{{ $section['title'] ?? '' }}</h2>
            <div class="text-sm leading-relaxed" style="color: var(--text-muted)">
                {!! nl2br(e($section['body'] ?? '')) !!}
            </div>
        </section>
    @empty
        <x-empty message="محتوى الصفحة بيتجهّز." />
    @endforelse
@endsection
