@extends('layouts.guest')
@section('title', setting('onboarding.placement.title', 'اختبار تمهيديّ سريع'))

@section('content')
    {{--
      2.5-د-2: الاختبار التمهيديّ — الأسئلة قد تكون فيديو و/أو كود HTML مضمَّنًا
      و/أو نصًّا و/أو صورة، والإجابات كإجابات الاختبارات العادية، **ولكلّ سؤال
      مكافأته بجانبه** (XP فقط أو تذاكر فقط أو الاثنين).
    --}}
    <div class="card w-full max-w-2xl flex flex-col" style="max-height: 92vh">

        <header class="px-5 py-4 shrink-0" style="border-bottom: 1px solid var(--border)">
            <h1 class="text-xl font-extrabold">{{ setting('onboarding.placement.title', 'اختبار تمهيديّ سريع') }}</h1>
            <div class="text-sm mt-2" style="color: var(--text-muted)">
                {!! setting('onboarding.placement.intro_html', '') !!}
            </div>
        </header>

        <form method="post" action="{{ route('onboarding.placement.submit') }}" class="flex flex-col overflow-hidden">
            @csrf

            <div class="px-5 py-4 overflow-y-auto space-y-5">
                @forelse ($questions as $i => $question)
                    <fieldset>
                        <legend class="flex flex-wrap items-baseline gap-2 mb-2">
                            <span class="text-xs" style="color: var(--text-muted)">{{ setting('onboarding.placement_view.text_1', 'سؤال') }} {{ $i + 1 }}</span>
                            <span class="font-semibold text-sm">{{ $question->prompt }}</span>

                            {{-- المكافأة بجانب السؤال — ورمزٌ مع كلّ لون (2.16) --}}
                            @if ($question->reward_xp > 0 || $question->reward_tickets > 0)
                                <span class="text-xs rounded-lg px-2 py-1"
                                      style="background: color-mix(in srgb, var(--color-state-honor) 14%, transparent); color: var(--text)">
                                    ★
                                    @if ($question->reward_xp > 0) {{ $question->reward_xp }} XP @endif
                                    @if ($question->reward_xp > 0 && $question->reward_tickets > 0) · @endif
                                    @if ($question->reward_tickets > 0) {{ $question->reward_tickets }} {{ setting('onboarding.placement_view.text_2', 'تذكرة') }} @endif
                                </span>
                            @endif
                        </legend>

                        {{-- وسيط السؤال: صورة أو فيديو أو كود مضمَّن يكتبه الأدمن --}}
                        @if ($question->media_kind === 'image' && $question->media_url)
                            <img src="{{ $question->media_url }}" alt="" class="rounded-xl mb-2" style="max-width: 100%">
                        @elseif ($question->media_kind === 'video' && $question->media_url)
                            <video src="{{ $question->media_url }}" controls class="rounded-xl mb-2" style="max-width: 100%"></video>
                        @elseif ($question->media_kind === 'embed' && $question->embed_html)
                            <div class="rounded-xl overflow-hidden mb-2">{!! $question->embed_html !!}</div>
                        @endif

                        @if ($question->type === 'choice' && $question->publicOptions() !== [])
                            <div class="space-y-2">
                                @foreach ($question->publicOptions() as $option)
                                    <label class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm"
                                           style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border)">
                                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option }}">
                                        <span>{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <input type="text" name="answers[{{ $question->id }}]"
                                   class="w-full rounded-xl px-3 py-2 text-sm" style="min-height: 44px;
                                          background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @endif
                    </fieldset>
                @empty
                    <p class="text-sm" style="color: var(--text-muted)">
                        {{ setting('onboarding.placement.empty_text', 'مافيش أسئلة دلوقتي — كمّل على طول.') }}
                    </p>
                @endforelse
            </div>

            <footer class="px-5 py-4 shrink-0" style="border-top: 1px solid var(--border)">
                <button type="submit" class="btn w-full rounded-xl py-2 font-semibold motion-standard"
                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                    {{ setting('onboarding.placement.submit_label', 'سلّم إجاباتي') }}
                </button>
            </footer>
        </form>
    </div>
@endsection
