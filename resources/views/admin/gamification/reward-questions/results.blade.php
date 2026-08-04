@extends('layouts.admin')

@section('title', setting('admin.gamification.reward_questions.results.ntayj_swal_almkafaa', 'نتائج سؤال المكافأة'))

@section('content')
    {{-- النتائج بعد الإغلاق (12.10-أ): كم حلّه · نسبة الصحّ · أسرع مجيب --}}
    <x-page-header
        :title="setting('admin.gamification.reward_questions.results.ntayj_swal_almkafaa', 'نتائج سؤال المكافأة')"
        :subtitle="\Illuminate\Support\Str::limit($question->prompt, 120)"
        :breadcrumbs="[
            ['label' => setting('admin.gamification.reward_questions.results.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
            ['label' => setting('admin.gamification.reward_questions.results.altlayb', 'التلعيب'), 'url' => route('admin.gamification.index', ['tab' => 'reward_questions'])],
            ['label' => setting('admin.gamification.reward_questions.results.alntayj', 'النتائج')],
        ]" />

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-kpi :label="setting('admin.gamification.reward_questions.results.mn_jawb', 'مَن جاوب')" :value="$results['answers']" />
        <x-kpi :label="setting('admin.gamification.reward_questions.results.ijabat_shyha', 'إجابات صحيحة')" :value="$results['correct']" />
        <x-kpi :label="setting('admin.gamification.reward_questions.results.nsba_alsh', 'نسبة الصحّ')" :value="$results['percent'].'%'" />
        <x-kpi :label="setting('admin.gamification.reward_questions.results.alhala', 'الحالة')" :value="$state['label']" />
    </div>

    <section class="card p-4 md:p-5">
        <h2 class="font-bold mb-3">{{ setting('admin.gamification.reward_questions.results.asra_mjyb_shyh', 'أسرع مجيب صحيح') }}</h2>

        @if ($results['fastest'])
            <div class="flex items-center gap-3">
                <x-avatar :name="$results['fastest']->user?->name ?? '—'" />
                <div>
                    <div class="font-semibold text-sm">{{ $results['fastest']->user?->name }}</div>
                    <p class="text-xs" style="color: var(--text-muted)">
                        {{ $results['fastest']->answered_at?->format('Y/m/d — H:i:s') }}
                        · +{{ $results['fastest']->xp_awarded }} XP
                        · +{{ $results['fastest']->tickets_awarded }} {{ setting('admin.gamification.reward_questions.results.tdhkra', 'تذكرة') }}
                    </p>
                </div>
            </div>
        @else
            <x-empty :message="setting('admin.gamification.reward_questions.results.mfysh_ijaba_shyha_lsh', 'مفيش إجابة صحيحة لسّه.')" />
        @endif

        <p class="text-xs mt-4" style="color: var(--text-muted)">
            {{ setting('admin.gamification.reward_questions.results.rabt_alswal', 'رابط السؤال:') }} <span class="font-mono">{{ $link }}</span>
        </p>
    </section>
@endsection
