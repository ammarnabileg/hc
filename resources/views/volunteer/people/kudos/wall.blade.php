@extends('layouts.volunteer')

@section('title', setting('volunteer.people_kudos_wall.title', 'حائط الشكر'))

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
        :title="setting('volunteer.people_kudos_wall.tooltip', 'حائط الشكر — نادي +').$thresholdText"
        :subtitle="setting('volunteer.people_kudos_wall.subtitle', 'السباق يتجدّد بعد ').$daysToReset.setting('volunteer.people_kudos_wall.subtitle_2', ' يومًا مع التصفير الشهريّ')"
        :breadcrumbs="[['label' => setting('volunteer.common.breadcrumb_root', 'لوحة التطوّع'), 'url' => url('/volunteer')], ['label' => setting('volunteer.people_kudos_wall.label', 'التقدير')], ['label' => setting('volunteer.people_kudos_wall.title', 'حائط الشكر')]]" />

    <x-tabs :current="$tab" :tabs="[
        ['key' => 'wall', 'label' => setting('volunteer.people_kudos_wall.label_2', 'الحائط'), 'url' => route('volunteer.kudos.wall', ['tab' => 'wall'])],
        ['key' => 'discussion', 'label' => setting('volunteer.people_kudos_wall.label_3', 'النقاش'), 'url' => route('volunteer.kudos.wall', ['tab' => 'discussion'])],
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
                          placeholder="{{ setting('volunteer.people_kudos_wall.placeholder', 'اكتب حاجة تستاهل تتقال') }}"></textarea>
                <button type="submit" class="btn mt-3 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.people_kudos_wall.action', 'انشر') }}</button>
            </form>
        @endif

        @if ($posts->isEmpty())
            <x-empty :message="setting('volunteer.people_kudos_wall.empty', 'النقاش لسّه فاضي — ابدأ إنت')" />
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
                            @foreach ([1 => setting('volunteer.people_kudos_wall.foreach', 'مفيد ▲'), -1 => setting('volunteer.people_kudos_wall.foreach_2', 'مش مفيد ▼')] as $value => $label)
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
                <p class="text-sm">{{ setting('volunteer.people_kudos_wall.text', 'مفيش حد بلغ +') }}{{ $thresholdText }} {{ setting('volunteer.people_kudos_wall.text_2', 'الشهر ده لسّه — المقعد مفتوح.') }}</p>
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
                                    {{ setting('volunteer.people_kudos_wall.text_3', 'الترتيب #') }}{{ $index + 1 }} · Rep {{ $row->score }}
                                </p>
                            </div>
                        </div>

                        <div class="relative mt-3">
                            <x-state-badge state="honor" :label="setting('volunteer.people_kudos_wall.label_4', 'نادي +').$thresholdText" />
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        {{-- بلوك «اقتربت» ببار تقدّم شخصيّ --}}
        <section class="card p-4">
            <h2 class="font-bold text-sm mb-3">{{ setting('volunteer.people_kudos_wall.heading', 'اقتربت') }}</h2>

            <div class="mb-4">
                <div class="flex items-center justify-between text-xs mb-1">
                    <span>{{ setting('volunteer.people_kudos_wall.text_4', 'رقمك دلوقتي') }} {{ $progress['score'] }}</span>
                    <span style="color: var(--text-muted)">{{ setting('volunteer.people_kudos_wall.text_5', 'باقي') }} {{ $progress['remaining'] }} {{ setting('volunteer.people_kudos_wall.text_6', 'للعتبة') }}</span>
                </div>
                <div class="h-2 rounded-full" style="background: var(--surface-sunken)">
                    <div class="h-2 rounded-full motion-standard"
                         style="width: {{ $progress['percent'] }}%; background: var(--color-brand-500)"></div>
                </div>
            </div>

            @if ($approaching->isEmpty())
                <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.people_kudos_wall.text_7', 'محدّش قرّب للعتبة الشهر ده لسّه.') }}</p>
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
