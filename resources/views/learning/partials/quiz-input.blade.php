@php
    /**
     * حقل إجابة سؤال الدرس (4): اختيار من متعدّد · نصّ · رقم بخانات OTP منفصلة.
     * وترتيب الاختيارات يأتي من المحاولة نفسها فلا يتبدّل بين الشاشة والمعاينة (4.1-1).
     */
    $value = (string) ($answers[(string) $question->id] ?? '');
    $length = $otp_lengths[$question->id] ?? 1;
    $choices = $options[$question->id] ?? array_values((array) $question->options);
    $digits = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
@endphp

@if ($question->type === 'otp')
    <div class="otp-row" role="group" aria-label="{{ $question->prompt }}">
        @for ($i = 0; $i < $length; $i++)
            <input type="text" name="digits[{{ $question->id }}][]" class="otp-box" maxlength="1"
                   inputmode="numeric" autocomplete="off" pattern="[0-9]*"
                   value="{{ $digits[$i] ?? '' }}"
                   aria-label="{{ setting('learning.questions.digit_label') }} {{ $i + 1 }}" required>
        @endfor
    </div>
@elseif ($question->type === 'choice')
    <div class="space-y-1">
        @foreach ($choices as $option)
            <label class="flex items-center gap-2 text-sm rounded-lg px-2 py-2"
                   style="background: var(--surface); min-block-size: 2.75rem">
                <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option }}"
                       @checked($value === (string) $option) required>
                <span>{{ $option }}</span>
            </label>
        @endforeach
    </div>
@else
    <input type="text" name="answers[{{ $question->id }}]" required value="{{ $value }}"
           placeholder="{{ $question->placeholder }}"
           class="w-full rounded-xl px-3 py-2 text-sm"
           style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
@endif
