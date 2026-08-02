@extends('layouts.app')

@section('title', $owner->shortName().' — السيرة الذاتيّة')
@section('meta_description', $headline !== '' ? $headline : ('السيرة الذاتيّة لـ'.$owner->shortName()))

@section('content')
    {{--
      الرابط العامّ للسيرة (9) — يعمل بلا تسجيل زيّ صفحة الشهادة،
      **وبلا أيّ بيان حسّاس**: الموبايل والبريد لا يخرجان منه إطلاقًا.
    --}}
    <article class="max-w-3xl mx-auto">
        <header class="card p-5 mb-4">
            <div class="flex flex-wrap items-center gap-4">
                {{-- الأفاتار بلا هالة (2.10.1-16) — وبالمقاس المناسب للسياق (2.7) --}}
                <x-avatar :user="$owner" size="20" />

                <div class="min-w-0">
                    <h1 class="text-xl md:text-2xl font-extrabold">{{ $owner->name }}</h1>

                    @if ($headline !== '')
                        <p class="text-sm mt-1" style="color: var(--text-muted)">{{ $headline }}</p>
                    @endif

                    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs" style="color: var(--text-muted)">
                        <span class="font-mono">#{{ $owner->code }}</span>
                        {{-- ⭐ المحافظة حقل عامّ دائمًا (12.14-د) --}}
                        @if ($owner->governorate)
                            <span>{{ $owner->country?->name_ar }} · {{ $owner->governorate->name_ar }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        @forelse ($sections as $section)
            <section class="card p-5 mb-3">
                <h2 class="font-bold text-sm mb-2">{{ $section['heading'] }}</h2>
                <ul class="space-y-1 text-sm">
                    @foreach ($section['lines'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </section>
        @empty
            {{-- الحالة الفارغة سطر واحد يشجّع ولا يعاتب (2.17-ج) --}}
            <x-empty :message="setting('cv.public.empty_message', 'السيرة لسّه فاضية — صاحبها بيجهّزها.')" />
        @endforelse

        <p class="text-xs text-center mt-4" style="color: var(--text-muted)">
            {{ setting('platform.identity.name', config('app.name')) }} · {{ now()->format('Y/m/d') }}
        </p>
    </article>
@endsection
