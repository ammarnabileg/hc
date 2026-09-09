@php
    // أنواع الأسئلة مشتركة وقابلة للتوسّع (4): اختيار من متعدّد · نصّيّة · رقميّة · OTP
    $options = is_array($q->options) ? $q->options : [];
    $name = 'answers['.$q->id.']';
    $otpLength = $otpLength ?? 1;
@endphp

@once
    @push('head')
        <style>
            /* الإدخال الرقميّ بنمط OTP (4): خانة لكلّ رقم — ومقاس اللمس 44×44 (2.15-ج) */
            .otp-row { display: flex; gap: 0.5rem; flex-wrap: wrap; direction: ltr; justify-content: flex-end; }
            .otp-box {
                inline-size: 2.75rem;
                block-size: 2.75rem;
                text-align: center;
                font-size: 1.1rem;
                font-weight: 700;
                border-radius: 0.75rem;
                background: var(--surface-sunken);
                border: 1px solid var(--border);
                color: var(--text);
            }
            .otp-box:focus { outline: 2px solid var(--color-brand-500); outline-offset: 1px; }
        </style>
    @endpush
@endonce

@if ($q->type === 'otp')
    {{-- السؤال الرقميّ المستنسَخ من درسٍ (4 · 24.5) بنفس خانات OTP لا نصًّا حرًّا --}}
    @php $digits = preg_split('//u', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: []; @endphp
    <div class="otp-row" role="group" aria-label="{{ $q->prompt }}">
        @for ($i = 0; $i < $otpLength; $i++)
            {{-- الاسم يضمن وصول الأرقام لو أُرسل الفورم خامًا (بلا JS أو عند تسليم انتهاء الوقت التلقائيّ) --}}
            <input type="text" class="otp-box" maxlength="1" name="digits[{{ $q->id }}][]" data-question="{{ $q->id }}"
                   inputmode="numeric" autocomplete="off" pattern="[0-9]*"
                   value="{{ $digits[$i] ?? '' }}"
                   aria-label="{{ setting('learning.questions.digit_label', 'خانة') }} {{ $i + 1 }}">
        @endfor
    </div>
@elseif ($q->type === 'choice' && $options !== [])
    <div class="space-y-2">
        @foreach ($options as $option)
            @php $option = is_array($option) ? ($option['text'] ?? '') : (string) $option; @endphp
            <label class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm cursor-pointer motion-standard"
                   style="background: var(--surface-sunken)">
                <input type="radio" name="{{ $name }}" value="{{ $option }}" data-question="{{ $q->id }}"
                       @checked((string) $value === (string) $option)>
                <span>{{ $option }}</span>
            </label>
        @endforeach
    </div>
@elseif ($q->type === 'number')
    {{-- السؤال الرقميّ بإدخالٍ واضح ومباشر (4) --}}
    <input type="text" inputmode="numeric" name="{{ $name }}" value="{{ $value }}" data-question="{{ $q->id }}"
           class="w-40 rounded-xl px-3 py-2 text-lg text-center tabular-nums"
           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
           aria-label="{{ setting('exams.labels.numeric_answer', 'الإجابة الرقميّة') }}">
@else
    <textarea name="{{ $name }}" rows="3" data-question="{{ $q->id }}"
              class="w-full rounded-xl px-3 py-2 text-sm"
              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
              aria-label="{{ setting('exams.labels.text_answer', 'إجابتك') }}">{{ $value }}</textarea>
@endif
