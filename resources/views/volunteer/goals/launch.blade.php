@extends('layouts.app')

@section('title', 'إطلاق الهدف')

@section('content')
    {{--
        ⭐ المعاينة النهائيّة و«إرسال للتنفيذ» (23 — 1.5 · 1.6).
        سؤال واحد للشاشة: «الهدف ده جاهز يتبعت ولّا ناقصه حاجة؟»
        وفعل رئيسيّ واحد لكلّ هدف: **إرسال للتنفيذ** (2.15-أ-2).
    --}}
    <x-page-header
        title="إطلاق الهدف"
        subtitle="بالضغطة بتبدأ نافذة التفكيك لكلّ طبقة — فالعدّ يبدأ من الإشعار لا من يوم ما اتكتبت المهمّة."
        :breadcrumbs="[['label' => 'الأهداف والمَعالِم', 'url' => route('volunteer.goals')], ['label' => 'إطلاق الهدف']]" />

    <div class="card p-3 mb-4 text-sm">
        <x-icon name="hourglass" size="16" />
        نافذة التفكيك الحاليّة <strong>{{ $windowHours }}</strong> ساعة لكلّ طبقة.
        <span style="color: var(--text-muted)">ومَن يختار ينفّذ مهمّته بنفسه مالوش خصم تفكيك أصلًا.</span>

        @can('goals.create')
            {{-- مدخل رحلة البناء (23 — 1.1 … 1.4) — وهي ما يسبق هذه الشاشة --}}
            <a class="block mt-2 text-xs hover:underline" style="color: var(--color-brand-500)"
               href="{{ route('volunteer.goals.build') }}">رحلة بناء الهدف — إنشاء وتفكيك وتجميع وتسعير</a>
        @endcan
    </div>

    <section class="space-y-3">
        @forelse ($rows as $row)
            @php $goal = $row['goal']; $gaps = $row['gaps']; @endphp

            <article class="card p-4">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <div class="font-semibold">{{ $goal->name }}</div>
                        <div class="text-xs mt-0.5" style="color: var(--text-muted)">
                            ينتهي {{ $goal->end_date?->format('Y-m-d') ?? 'بلا تاريخ' }}
                        </div>
                    </div>

                    {{-- اللون مع رمز ونصّ دائمًا (2.16) --}}
                    <x-state-badge :state="$gaps === [] ? 'ok' : 'warn'"
                                   :label="$gaps === [] ? 'جاهز للإرسال' : 'ناقصه '.count($gaps)" />
                </div>

                @if ($goal->description)
                    <p class="text-sm mt-2">{{ $goal->description }}</p>
                @endif

                @if ($gaps !== [])
                    {{-- «بقائمة النواقص» — الرسالة تقول ماذا حدث وماذا تفعل (2.17-ب) --}}
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach ($gaps as $gap)
                            <li><x-state-badge state="warn" label="ناقص" /> {{ $gap }}</li>
                        @endforeach
                    </ul>
                @else
                    <form method="post" action="{{ route('volunteer.goals.launch.send', $goal) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">إرسال للتنفيذ</button>
                    </form>
                @endif
            </article>
        @empty
            <x-empty message="مفيش أهداف مستنية الإطلاق — كلّها اتبعتت للتنفيذ."
                     action="شوف الأهداف الجارية" :href="route('volunteer.goals')" />
        @endforelse
    </section>
@endsection
