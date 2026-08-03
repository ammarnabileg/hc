@extends('layouts.admin')

@section('title', 'الدرس: '.$lesson->title_ar)

@section('content')
    {{-- بناء الدرس (12.4-ج): فيديو/كود/مرفقات أو نصّ + تبويب أسئلة --}}
    <x-page-header
        :title="$lesson->title_ar"
        subtitle="الدرس فيديو يوتيوب بكود ومرفقات، أو نصّ منسّق — وتحته أسئلته."
        :breadcrumbs="[
            ['label' => 'التدريبات', 'url' => route('admin.courses.index')],
            ['label' => $course->name_ar, 'url' => route('admin.courses.edit', $course)],
            ['label' => $section->title_ar],
            ['label' => $lesson->title_ar],
        ]" />

    <div class="grid lg:grid-cols-2 gap-4">
        {{-- ------------------------------------------------ محتوى الدرس --}}
        <section class="card p-4 space-y-3">
            <h2 class="font-bold">محتوى الدرس</h2>

            <form method="post" action="{{ route('admin.lessons.update', $lesson) }}" class="space-y-3">
                @csrf @method('put')

                <x-form.input name="title_ar" label="العنوان" :value="$lesson->title_ar" required />

                <label class="block">
                    <span class="block text-sm mb-1">النوع</span>
                    <select name="type" class="w-full rounded-xl px-3 py-2 text-sm" data-lesson-type
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="video" @selected($lesson->type === 'video')>فيديو يوتيوب</option>
                        <option value="document" @selected($lesson->type === 'document')>نصّ</option>
                    </select>
                </label>

                <div data-lesson-video class="space-y-3 {{ $lesson->type === 'video' ? '' : 'hidden' }}">
                    <x-form.input name="video_url" label="رابط اليوتيوب" :value="$lesson->video_id"
                                  hint="الـID والثامبنيل بيتستخرجوا تلقائيًّا." />
                    @if ($lesson->video_id)
                        <img src="https://img.youtube.com/vi/{{ $lesson->video_id }}/mqdefault.jpg"
                             alt="معاينة الفيديو" loading="lazy" class="rounded-xl max-w-full">
                    @endif
                    <label class="block">
                        <span class="block text-sm mb-1">كود/HTML تابع</span>
                        <textarea name="embed_html" rows="3" class="w-full rounded-xl px-3 py-2 text-sm font-mono"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('embed_html', $lesson->embed_html) }}</textarea>
                    </label>
                </div>

                <label class="block" data-lesson-text>
                    <span class="block text-sm mb-1">النصّ</span>
                    <textarea name="content" rows="6" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('content', $lesson->content) }}</textarea>
                </label>

                {{-- المرفقات من المكتبة المركزيّة — يترفع مرّة ويُعاد استخدامه (12.4-د) --}}
                <fieldset class="card p-3">
                    <legend class="text-sm px-1">مرفقات من مكتبة الوسائط</legend>
                    <div class="grid md:grid-cols-2 gap-2 mt-2 max-h-48 overflow-y-auto">
                        @php $attached = $attachments->pluck('media_item_id')->all(); @endphp
                        @foreach ($mediaItems as $item)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="attachment_ids[]" value="{{ $item->id }}"
                                       @checked(in_array($item->id, $attached, true))>
                                <span class="truncate">{{ $item->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <a href="{{ route('admin.media.index') }}" class="text-xs underline mt-2 inline-block">افتح المكتبة</a>
                </fieldset>

                <div class="grid md:grid-cols-2 gap-3">
                    <x-form.input name="duration_minutes" label="المدّة (دقائق)" type="number" :value="$lesson->duration_minutes" />
                    <label class="flex items-center gap-2 text-sm mt-6">
                        <input type="hidden" name="is_free_preview" value="0">
                        <input type="checkbox" name="is_free_preview" value="1" @checked($lesson->is_free_preview)> معاينة مجّانيّة
                    </label>
                </div>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">حفظ الدرس</button>
            </form>

            {{-- نقل الدرس بين السيكشنز + تكرار (12.4-هـ) --}}
            <details>
                <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">نقل أو تكرار</summary>
                <div class="mt-3 space-y-3">
                    <form method="post" action="{{ route('admin.lessons.move', $lesson) }}" class="flex gap-2">
                        @csrf
                        <select name="section_id" class="flex-1 rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($sections as $target)
                                <option value="{{ $target->id }}" @selected($target->id === $section->id)>{{ $target->title_ar }}</option>
                            @endforeach
                        </select>
                        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">انقل</button>
                    </form>

                    <form method="post" action="{{ route('admin.lessons.duplicate', $lesson) }}">
                        @csrf
                        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">تكرار الدرس</button>
                    </form>
                </div>
            </details>
        </section>

        {{-- ------------------------------------------------ تبويب الأسئلة --}}
        <section class="card p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-bold">أسئلة الدرس</h2>
                <button type="button" data-modal-open="csv-import" class="text-xs underline">استيراد CSV</button>
            </div>

            @if (session('import_errors'))
                <div class="card p-3 text-sm" style="border-color: var(--color-state-danger)">
                    <div class="font-semibold mb-1">صفوف محتاجة مراجعة:</div>
                    <ul class="space-y-1">
                        @foreach (session('import_errors') as $error)
                            <li>صفّ {{ $error['row'] }}: {{ $error['message'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @forelse ($questions as $question)
                <div class="card p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold">{{ $question->prompt }}</div>
                            <div class="text-xs mt-1" style="color: var(--text-muted)">
                                {{ ['otp' => 'رقميّ OTP', 'choice' => 'اختيار متعدّد', 'text' => 'نصّيّ'][$question->type] ?? $question->type }}
                                · Placeholder: {{ $question->placeholder ?: '—' }}
                            </div>
                        </div>
                        @if ($question->is_general)
                            <x-state-badge state="honor" label="سؤال عامّ" />
                        @endif
                    </div>
                    <div class="flex gap-2 mt-2">
                        <form method="post" action="{{ route('admin.questions.general', $question) }}">
                            @csrf
                            <button class="text-xs underline">{{ $question->is_general ? 'شيله من البنك' : 'اجعله عامًّا' }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.questions.destroy', $question) }}"
                              onsubmit="return confirm('نشيل السؤال؟')">
                            @csrf @method('delete')
                            <button class="text-xs underline" style="color: var(--color-state-danger)">حذف</button>
                        </form>
                    </div>
                </div>
            @empty
                <x-empty message="مفيش أسئلة لسّه — ضيف أوّل سؤال." />
            @endforelse

            <form method="post" action="{{ route('admin.questions.store', $lesson) }}" class="space-y-3 card p-3">
                @csrf
                <label class="block">
                    <span class="block text-sm mb-1">النوع</span>
                    <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="otp">رقميّ OTP</option>
                        <option value="choice">اختيار متعدّد</option>
                        <option value="text">نصّيّ</option>
                    </select>
                </label>

                <x-form.input name="prompt" label="نصّ السؤال" required />
                {{-- ⭐ نصّ Placeholder داخل الحقل (12.4-ج) --}}
                <x-form.input name="placeholder" label="نصّ داخل الحقل (Placeholder)"
                              :placeholder="setting('lessons.questions.placeholder_otp', 'اكتب الرقم')" />

                <label class="block">
                    <span class="block text-sm mb-1">الخيارات (سطر لكلّ خيار)</span>
                    <textarea name="options" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <x-form.input name="correct_answer" label="الإجابة الصحيحة" />

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_general" value="1"> سؤال عامّ (يدخل بنك الامتحان النهائيّ)
                </label>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">أضِف السؤال</button>
            </form>
        </section>
    </div>

    <x-modal id="csv-import" title="استيراد أسئلة CSV">
        <form method="post" action="{{ route('admin.questions.import', $lesson) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <p class="text-sm" style="color: var(--text-muted)">
                أعمدة الملفّ: {{ implode(' · ', $csvColumns) }} — والخيارات تُفصَل بعلامة |
            </p>
            <input type="file" name="file" accept=".csv,text/csv" required class="w-full text-sm">
            <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">استورد</button>
        </form>
    </x-modal>

    @include('admin.courses.partials.toast')

    @push('scripts')
        <script>
            /* تبديل حقول الدرس حسب نوعه — بلا إعادة تحميل */
            const typeSelect = document.querySelector('[data-lesson-type]');
            const videoBlock = document.querySelector('[data-lesson-video]');

            typeSelect?.addEventListener('change', () => {
                videoBlock?.classList.toggle('hidden', typeSelect.value !== 'video');
            });
        </script>
    @endpush
@endsection
