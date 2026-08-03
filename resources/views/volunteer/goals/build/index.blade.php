@extends('layouts.app')

@section('title', 'رحلة بناء الهدف')

@php
    /**
     * ⭐ لوحة رحلة بناء الهدف — المرحلة صفر (23 — 1.1 … 1.4).
     *
     * سؤال واحد للشاشة: «الأهداف اللي لسّه بتتبني، كلّ واحد فيها واقف فين ودوري إيه؟»
     * وفعل رئيسيّ واحد: **هدف جديد** — والباقي داخل كارت كلّ هدف (2.15-أ-2).
     *
     * ولا شيء هنا يظهر لمن لا دور له في الرحلة: الفلترة على الخادم بـ`canSeeBuild`،
     * فالكوردنيتور يفتح الصفحة فيجدها فارغة — لا يعرف حتى أنّ الهدف موجود.
     */
@endphp

@section('content')
    <x-page-header
        title="رحلة بناء الهدف"
        subtitle="من الإنشاء للتفكيك للملء للتسعير — ومحدّش من المنفّذين شايف حاجة قبل «إرسال للتنفيذ»."
        :breadcrumbs="[['label' => 'الأهداف والمَعالِم', 'url' => route('volunteer.goals')], ['label' => 'رحلة بناء الهدف']]">
        @if ($canCreate)
            <x-slot:action>
                <a href="{{ route('volunteer.goals.build.create') }}"
                   class="btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">
                    <x-icon name="plus" size="16" /> هدف جديد
                </a>
            </x-slot:action>
        @endif
    </x-page-header>

    @if (session('status'))
        <div class="card p-3 mb-4 text-sm"><x-state-badge state="ok" label="تمام" /> {{ session('status') }}</div>
    @endif

    @forelse ($goals as $goal)
        @php
            $tracks = $tracksOf[$goal->id] ?? [];
            $stage = $goal->build_stage ?? 'draft';
        @endphp

        <article class="card p-4 mb-3">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <h2 class="font-bold">{{ $goal->name }}</h2>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                        ينتهي {{ $goal->end_date?->format('Y/m/d') ?? '—' }}
                        @if ($tracks)
                            · مسارات: {{ implode(' · ', $tracks) }}
                        @else
                            · <span style="color: var(--color-state-warn)">لسّه مش مربوط بمسار — محدّش شايفه</span>
                        @endif
                    </div>
                </div>

                <x-state-badge :state="$stage === 'preview' ? 'ok' : ($tracks ? 'warn' : 'idle')"
                               :label="$stages[$stage] ?? $stage" />
            </div>

            @if ($goal->reason)
                <p class="text-sm mt-2"><span style="color: var(--text-muted)">سبب الهدف:</span> {{ $goal->reason }}</p>
            @endif

            {{-- فعل رئيسيّ واحد ظاهر، والباقي في «⋯» (2.15-أ-2) --}}
            <div class="mt-3 flex items-center gap-2 flex-wrap">
                @if ($canBreakdown)
                    <a href="{{ route('volunteer.goals.build.breakdown', $goal) }}"
                       class="btn rounded-xl px-3 py-2 text-xs font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">تفكيك الهدف</a>
                @elseif ($canFill)
                    <a href="{{ route('volunteer.goals.build.fill', $goal) }}"
                       class="btn rounded-xl px-3 py-2 text-xs font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">املا حزم كيانك</a>
                @endif

                <details class="relative">
                    <summary class="cursor-pointer rounded-xl px-3 py-2 text-xs"
                             style="background: var(--surface-sunken); border: 1px solid var(--border)">⋯</summary>
                    <div class="card p-2 mt-1 text-xs space-y-1" style="min-width: 12rem">
                        @if ($canAggregate)
                            <a class="block px-2 py-1 rounded hover:opacity-80"
                               href="{{ route('volunteer.goals.build.aggregate', $goal) }}">التجميع والتسعير</a>
                        @endif
                        @if ($canFill)
                            <a class="block px-2 py-1 rounded hover:opacity-80"
                               href="{{ route('volunteer.goals.build.fill', $goal) }}">حزم كياني</a>
                        @endif
                        @if ($canCreate && ! $tracks)
                            <button type="button" data-modal-open="link-{{ $goal->id }}"
                                    class="block w-full text-right px-2 py-1 rounded hover:opacity-80">اربطه بمسار</button>
                        @endif
                    </div>
                </details>
            </div>
        </article>
    @empty
        <x-empty message="مافيش أهداف تحت البناء دلوقتي." />
    @endforelse

    @push('modals')
        @if ($canCreate)
            @foreach ($goals as $goal)
                @continue(($tracksOf[$goal->id] ?? []) !== [])
                <x-modal :id="'link-'.$goal->id" title="اربط الهدف بمسار أو أكثر">
                    <form method="post" action="{{ route('volunteer.goals.build.tracks', $goal) }}" class="space-y-3 text-sm">
                        @csrf
                        <p style="color: var(--text-muted)">
                            الهدف مايظهرش لحدّ قبل الربط — وبالربط بيوصل الإشعار لمشرفي المسارات دي وحدهم.
                        </p>
                        @foreach ($tracks as $track)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="tracks[]" value="{{ $track->id }}">
                                <span>{{ $track->name_ar }}</span>
                            </label>
                        @endforeach
                        <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">اربط ووصّل الإشعار</button>
                    </form>
                </x-modal>
            @endforeach
        @endif
    @endpush
@endsection
