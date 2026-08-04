@extends('layouts.guest')
@section('title', setting('onboarding.accepted.title', 'تمّ قبول حسابك'))

@section('content')
    {{--
      2.5-د-4: صفحة «تمّ قبول حسابك» — تهنئة **مميّزة فيها احتفالات**،
      وتحتها **قسم قابل للتعديل (كود HTML يضيفه الأدمن)** من لوحة الإدارة،
      ثمّ يدخل المنصّة. وكانت قبل اليوم حدثَ احتفالٍ بلا صفحة تحمله.
    --}}
    @if ($celebration)
        @include('onboarding.partials.celebration', ['celebration' => $celebration])
    @endif

    <div class="card p-8 w-full max-w-lg text-center animate-fadeup" style="position: relative; z-index: 41">
        <div class="mx-auto mb-4" style="color: var(--color-state-honor)" aria-hidden="true">
            {{-- أيقونة SVG مرسومة — بلا مكتبات (قاعدة الأيقونات) --}}
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" style="margin-inline: auto">
                <path d="M4 7 7 17h10l3-10-4.5 3L12 4 8.5 10z" />
                <path d="M6 20h12" />
            </svg>
        </div>

        <h1 class="text-2xl font-extrabold">{{ setting('onboarding.accepted.title', 'تمّ قبول حسابك 🎉') }}</h1>

        {{-- ★ رمز مع اللون — فاللون وحده لا يحمل المعنى (2.16) --}}
        <p class="mt-2 text-sm" style="color: var(--color-state-honor)">★ {{ auth()->user()->shortName() }}، {{ setting('onboarding.accepted.text_1', 'أهلًا بيك.') }}</p>

        <div class="mt-4 text-sm text-start prose-onboarding" style="line-height: 1.9">
            {!! setting('onboarding.accepted.html', '') !!}
        </div>

        <form method="post" action="{{ route('onboarding.accepted.enter') }}" class="mt-6">
            @csrf
            <button class="btn w-full rounded-xl py-2 font-semibold motion-standard"
                    style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                {{ setting('onboarding.accepted.cta_label', 'يلا ندخل') }}
            </button>
        </form>
    </div>

    <style>
        .prose-onboarding h2 { font-size: 1.05rem; font-weight: 800; margin-block: 1rem .35rem; }
        .prose-onboarding h3 { font-size: .95rem; font-weight: 700; margin-block: .9rem .3rem; }
        .prose-onboarding p, .prose-onboarding li { margin-block-end: .5rem; }
        .prose-onboarding ul, .prose-onboarding ol { padding-inline-start: 1.25rem; }
        .prose-onboarding img, .prose-onboarding video, .prose-onboarding iframe {
            max-width: 100%; height: auto; border-radius: .75rem; margin-block: .75rem;
        }
    </style>
@endsection
