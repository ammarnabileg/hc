@php
    /**
     * أسئلة الدرس (4 · 4.1): لكلّ سؤال فورم مستقلّ يُرسَل للخادم — فالتصحيح
     * Server-side إلزاميّ، والـXP تُمنَح مرّةً واحدة لكلّ سؤال بقيدٍ فريد في القاعدة.
     * والإجابة الخاطئة تُشرَح بلا عقوبة ولا استهلاك محاولة صحيحة (24.5).
     */
@endphp

<div class="space-y-4">
    @foreach ($questions as $question)
        @php
            $answer = $answers[$question->id] ?? null;
            $solved = (bool) $answer?->is_correct;
            $length = $otp_lengths[$question->id] ?? 1;
        @endphp

        <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
            <div class="flex items-start justify-between gap-2 mb-2">
                <p class="text-sm font-semibold">{{ $question->prompt }}</p>
                <x-state-badge :state="$solved ? 'ok' : 'idle'"
                               :label="$solved ? setting('learning.questions.solved_label') : setting('learning.questions.open_label')" />
            </div>

            @if ($solved)
                <p class="text-xs" style="color: var(--text-muted)">
                    {{ setting('learning.questions.already_message') }}
                    @if ((int) $answer->xp_awarded > 0)
                        · +{{ (int) $answer->xp_awarded }} {{ setting('learning.xp.suffix') }}
                    @endif
                </p>
            @else
                <form method="post" action="{{ route('learning.lesson.answer', [$course, $lesson, $question]) }}"
                      class="space-y-2" data-otp-form>
                    @csrf

                    @if ($question->type === 'otp')
                        {{-- الإدخال الرقميّ بنمط OTP: خانة لكلّ رقم (4) — ويعمل بلا JS --}}
                        <div class="otp-row" role="group" aria-label="{{ $question->prompt }}">
                            @for ($i = 0; $i < $length; $i++)
                                <input type="text" name="digits[]" class="otp-box" maxlength="1"
                                       inputmode="numeric" autocomplete="off" pattern="[0-9]*"
                                       aria-label="{{ setting('learning.questions.digit_label') }} {{ $i + 1 }}" required>
                            @endfor
                        </div>
                    @elseif ($question->type === 'choice')
                        <div class="space-y-1">
                            @foreach ((array) $question->options as $option)
                                <label class="flex items-center gap-2 text-sm rounded-lg px-2 py-1.5"
                                       style="background: var(--surface)">
                                    <input type="radio" name="answer" value="{{ $option }}" required>
                                    <span>{{ $option }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <input type="text" name="answer" required
                               placeholder="{{ $question->placeholder }}"
                               class="w-full rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    @endif

                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        @if ((int) $question->xp_reward > 0)
                            <span class="text-xs" style="color: var(--text-muted)">
                                {{ setting('learning.questions.reward_label') }} +{{ (int) $question->xp_reward }} {{ setting('learning.xp.suffix') }}
                            </span>
                        @else
                            <span></span>
                        @endif

                        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">
                            {{ setting('learning.questions.submit') }}
                        </button>
                    </div>

                    @if ($answer && ! $solved)
                        <p class="text-xs" style="color: var(--color-state-warn)">{{ setting('learning.questions.retry_hint') }}</p>
                    @endif
                </form>
            @endif
        </div>
    @endforeach
</div>
