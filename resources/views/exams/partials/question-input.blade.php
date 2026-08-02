@php
    // أنواع الأسئلة مشتركة وقابلة للتوسّع (4): اختيار من متعدّد · نصّيّة · رقميّة
    $options = is_array($q->options) ? $q->options : [];
    $name = 'answers['.$q->id.']';
@endphp

@if ($q->type === 'choice' && $options !== [])
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
