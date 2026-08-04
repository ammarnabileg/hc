@extends('layouts.app')

@section('title', setting('growth.profile_completion.title', 'أكمل ملفّك'))

@section('content')
    <div class="max-w-xl mx-auto">
        <x-page-header :title="setting('growth.profile_completion.title', 'أكمل ملفّك')"
                       :subtitle="setting('growth.profile_completion.subtitle', 'بياناتك الكاملة بتخلّي شهادتك وبطاقتك يطلعوا صحّ.')" />

        @if ($state['granted'])
            {{-- احتفال اللحظة: ردّ فوريّ ورقمٌ ظاهر (2.17-أ) --}}
            <x-toast :message="str_replace('{tickets}', $state['tickets'], (string) setting('growth.profile_completion.granted', 'تمام! ملفّك اكتمل و{tickets} تذاكر اتضافت لمحفظتك ✓'))" />
        @endif

        <div class="card p-5">
            <div class="flex items-end justify-between gap-3">
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('growth.profile_completion.progress_label', 'نسبة الاكتمال') }}</p>
                <p class="text-3xl font-extrabold tabular-nums" data-count-to="{{ $state['percent'] }}">{{ $state['percent'] }}</p>
            </div>

            <div class="mt-3 h-3 rounded-full overflow-hidden" style="background: var(--surface-sunken)"
                 role="progressbar" aria-valuenow="{{ $state['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full motion-standard" style="width: {{ $state['percent'] }}%; background: var(--color-brand-500)"></div>
            </div>

            <p class="text-sm mt-3" style="color: var(--text-muted)">
                @if ($state['rewarded'])
                    {{ setting('growth.profile_completion.already', 'مكافأة إكمال الملفّ اتصرفت قبل كده — وبتتصرف مرّة واحدة بس.') }}
                @else
                    {{ str_replace('{tickets}', $state['tickets'], (string) setting('growth.profile_completion.promise', 'أول ما توصل 100% هتاخد {tickets} تذاكر — مرّة واحدة.')) }}
                @endif
            </p>
        </div>

        <h2 class="font-extrabold mt-6 mb-2">{{ setting('growth.profile_completion.fields_label', 'اللي لسّه ناقص') }}</h2>

        @if ($state['missing'] === [])
            <x-empty :message="setting('growth.profile_completion.complete', 'ملفّك كامل — تمام كده.')" />
        @else
            <ul class="card divide-y" style="border-color: var(--border)">
                @foreach ($state['missing'] as $key => $label)
                    <li class="px-4 py-3 flex items-center justify-between gap-3">
                        <span class="text-sm">{{ $label }}</span>
                        {{-- رمزٌ مع اللون دائمًا (2.16-ب) --}}
                        <x-state-badge state="warn" label="{{ setting('growth.profile_completion.label_1', 'ناقص') }}" />
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-4">
            <a href="{{ \Illuminate\Support\Facades\Route::has('settings.index') ? route('settings.index') : url('/settings') }}"
               class="btn hidden md:inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">
                {{ setting('growth.profile_completion.cta', 'روح كمّل بياناتك') }}
            </a>
        </div>
    </div>
@endsection

@section('mobile_action')
    <a href="{{ \Illuminate\Support\Facades\Route::has('settings.index') ? route('settings.index') : url('/settings') }}"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">
        {{ setting('growth.profile_completion.cta', 'روح كمّل بياناتك') }}
    </a>
@endsection
