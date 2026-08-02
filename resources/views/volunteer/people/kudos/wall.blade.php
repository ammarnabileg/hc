@extends('layouts.app')

@section('title', 'حائط الشكر')

@php
    /**
     * حائط الشكر — نادي التميّز (13.4-ي · 24.4-11).
     * كروت بإطار ذهبيّ ولمعان `shimmer` — و**بلا هالة حول الأفاتار** (قاعدة صريحة 2.10.1).
     * والذهبيّ شرفٌ لا حالة تشغيليّة (2.16-أ).
     */
    $thresholdText = rtrim(rtrim(number_format($threshold, 2), '0'), '.');
@endphp

@section('content')
    <x-page-header
        :title="'حائط الشكر — نادي +'.$thresholdText"
        :subtitle="'السباق يتجدّد بعد '.$daysToReset.' يومًا مع التصفير الشهريّ'"
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/volunteer')], ['label' => 'التقدير'], ['label' => 'حائط الشكر']]" />

    <x-tabs :current="$tab" :tabs="[
        ['key' => 'wall', 'label' => 'الحائط', 'url' => route('volunteer.kudos.wall', ['tab' => 'wall'])],
        ['key' => 'discussion', 'label' => 'النقاش', 'url' => route('volunteer.kudos.wall', ['tab' => 'discussion'])],
        ['key' => 'kudos', 'label' => 'Kudos', 'url' => route('volunteer.kudos')],
    ]" />

    @if ($celebration)
        {{-- دخول النادي ⟵ احتفال ذروة (2.14) — والرسالة تنادي باسمه (2.17-أ) --}}
        <div class="card p-4 mb-4 animate-fadeup" style="border-color: var(--color-state-honor)">
            <p class="text-sm font-bold" style="color: var(--color-state-honor)">★ {{ $celebration['message'] }}</p>
        </div>
    @endif

    @if ($tab === 'discussion')
        @if ($canPost)
            <form method="post" action="{{ route('volunteer.kudos.wall.post') }}" class="card p-4 mb-4">
                @csrf
                <textarea name="body" rows="3" required class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"
                          placeholder="اكتب حاجة تستاهل تتقال"></textarea>
                <button type="submit" class="btn mt-3 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">انشر</button>
            </form>
        @endif

        @if ($posts->isEmpty())
            <x-empty message="النقاش لسّه فاضي — ابدأ إنت" />
        @else
            <div class="space-y-3">
                @foreach ($posts as $post)
                    <article class="card p-4">
                        <div class="flex items-center gap-2">
                            <x-avatar :user="$post->user" size="9" />
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-bold truncate">{{ $post->user?->name }}</div>
                                <div class="text-xs" style="color: var(--text-muted)">{{ $post->created_at->diffForHumans() }}</div>
                            </div>
                        </div>

                        <p class="mt-3 text-sm leading-7">{{ $post->body }}</p>

                        <div class="mt-3 flex items-center gap-2">
                            @foreach ([1 => 'مفيد ▲', -1 => 'مش مفيد ▼'] as $value => $label)
                                <form method="post" action="{{ route('volunteer.kudos.wall.vote', $post) }}">
                                    @csrf
                                    <input type="hidden" name="value" value="{{ $value }}">
                                    <button type="submit" class="btn rounded-xl px-3 py-2 text-xs" style="background: var(--surface-sunken)">{{ $label }}</button>
                                </form>
                            @endforeach
                            <span class="text-xs" style="color: var(--text-muted)">{{ $post->votes }}</span>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @else
        @if ($members->isEmpty())
            <div class="card p-6 text-center mb-4">
                <p class="text-sm">مفيش حد بلغ +{{ $thresholdText }} الشهر ده لسّه — المقعد مفتوح.</p>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-3 mb-6">
                @foreach ($members as $index => $row)
                    {{-- إطار ذهبيّ ولمعان — وبلا أيّ هالة حول الأفاتار --}}
                    <article class="card p-4 relative overflow-hidden animate-fadeup"
                             style="border: 1px solid var(--color-state-honor)">
                        <div class="absolute inset-0 animate-shimmer pointer-events-none" aria-hidden="true"></div>

                        <div class="relative flex items-center gap-3">
                            <x-avatar :user="$row->user" size="12" />
                            <div class="min-w-0">
                                <h2 class="font-bold text-sm truncate">{{ $row->user->name }}</h2>
                                <p class="text-xs" style="color: var(--text-muted)">
                                    الترتيب #{{ $index + 1 }} · Rep {{ $row->score }}
                                </p>
                            </div>
                        </div>

                        <div class="relative mt-3">
                            <x-state-badge state="honor" :label="'نادي +'.$thresholdText" />
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        {{-- بلوك «اقتربت» ببار تقدّم شخصيّ --}}
        <section class="card p-4">
            <h2 class="font-bold text-sm mb-3">اقتربت</h2>

            <div class="mb-4">
                <div class="flex items-center justify-between text-xs mb-1">
                    <span>رقمك دلوقتي {{ $progress['score'] }}</span>
                    <span style="color: var(--text-muted)">باقي {{ $progress['remaining'] }} للعتبة</span>
                </div>
                <div class="h-2 rounded-full" style="background: var(--surface-sunken)">
                    <div class="h-2 rounded-full motion-standard"
                         style="width: {{ $progress['percent'] }}%; background: var(--color-brand-500)"></div>
                </div>
            </div>

            @if ($approaching->isEmpty())
                <p class="text-xs" style="color: var(--text-muted)">محدّش قرّب للعتبة الشهر ده لسّه.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($approaching as $row)
                        <li class="flex items-center gap-2">
                            <x-avatar :user="$row->user" size="8" />
                            <span class="text-sm flex-1 truncate">{{ $row->user->name }}</span>
                            <span class="text-xs" style="color: var(--text-muted)">{{ $row->score }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
@endsection
