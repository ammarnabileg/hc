@extends('layouts.app')

@section('title', 'نتائج سؤال المكافأة')

@section('content')
    {{-- النتائج بعد الإغلاق (12.10-أ): كم حلّه · نسبة الصحّ · أسرع مجيب --}}
    <x-page-header
        title="نتائج سؤال المكافأة"
        :subtitle="\Illuminate\Support\Str::limit($question->prompt, 120)"
        :breadcrumbs="[
            ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
            ['label' => 'التلعيب', 'url' => route('admin.gamification.index', ['tab' => 'reward_questions'])],
            ['label' => 'النتائج'],
        ]" />

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-kpi label="مَن جاوب" :value="$results['answers']" />
        <x-kpi label="إجابات صحيحة" :value="$results['correct']" />
        <x-kpi label="نسبة الصحّ" :value="$results['percent'].'%'" />
        <x-kpi label="الحالة" :value="$state['label']" />
    </div>

    <section class="card p-4 md:p-5">
        <h2 class="font-bold mb-3">أسرع مجيب صحيح</h2>

        @if ($results['fastest'])
            <div class="flex items-center gap-3">
                <x-avatar :name="$results['fastest']->user?->name ?? '—'" />
                <div>
                    <div class="font-semibold text-sm">{{ $results['fastest']->user?->name }}</div>
                    <p class="text-xs" style="color: var(--text-muted)">
                        {{ $results['fastest']->answered_at?->format('Y/m/d — H:i:s') }}
                        · +{{ $results['fastest']->xp_awarded }} XP
                        · +{{ $results['fastest']->tickets_awarded }} تذكرة
                    </p>
                </div>
            </div>
        @else
            <x-empty message="مفيش إجابة صحيحة لسّه." />
        @endif

        <p class="text-xs mt-4" style="color: var(--text-muted)">
            رابط السؤال: <span class="font-mono">{{ $link }}</span>
        </p>
    </section>
@endsection
