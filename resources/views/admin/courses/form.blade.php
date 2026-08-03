@extends('layouts.admin')

@section('title', $course->exists ? 'تعديل تدريب' : 'تدريب جديد')

@section('content')
    @php
        $action = $course->exists ? route('admin.courses.update', $course) : route('admin.courses.store');
        $formTabs = [
            'data' => 'البيانات',
            'pricing' => 'التسعير',
            'availability' => 'الإتاحة',
            'grading' => 'التقييم',
            'content' => 'المحتوى',
        ];

        /* ⭐ مسوّدة التحرير المعلّقة **تُعاد إلى الحقول** (12.4-ب): الحفظ التلقائيّ
           على تدريبٍ حيّ لا يمسّ المنشور، فلو لم ترجع هنا لضاع عمل المحرّر لحظة
           ضغطه «حفظ» — لأنّ الفورم كان سيرسل قيم النسخة المنشورة فوقها. */
        $draft = $pendingDraft ?? [];
        $draftValue = fn (string $field, $fallback = null) => $draft[$field] ?? $fallback;
        $draftLabels = [
            'name_ar' => 'اسم العرض (عربيّ)',
            'name_en' => 'اسم العرض (إنجليزيّ)',
            'cert_name_ar' => 'اسم الشهادة (عربيّ)',
            'cert_name_en' => 'اسم الشهادة (إنجليزيّ)',
            'description_ar' => 'الوصف (عربيّ)',
            'description_en' => 'الوصف (إنجليزيّ)',
            'price_coins' => 'السعر الأساسيّ',
            'offer_price_coins' => 'سعر العرض',
            'paywall_text_ar' => 'نصّ الـPaywall (عربيّ)',
            'paywall_text_en' => 'نصّ الـPaywall (إنجليزيّ)',
            'deadline_days' => 'الديدلاين (أيّام)',
            'xp_max' => 'أقصى XP للدرس',
        ];
    @endphp

    <x-page-header
        :title="$course->exists ? $course->name_ar : 'تدريب جديد'"
        subtitle="املأ التابات على مهلك — بنحفظ مسودّة تلقائيًّا فما بيضيعش شغلك."
        :breadcrumbs="[
            ['label' => 'التدريبات', 'url' => route('admin.courses.index')],
            ['label' => $course->exists ? $course->name_ar : 'جديد'],
        ]" />

    {{-- ⭐ مسوّدة تحرير معلّقة على تدريبٍ حيّ (12.4-ب): الحفظ التلقائيّ لا يمسّ
         المنشور، فشغلك محفوظ هنا ومعروض في الحقول حتى تختار «حفظ» فيسري على
         الناس، أو «تجاهل المسودّة» فترجع النسخة المنشورة كما هي. --}}
    @if (! empty($draft))
        <div class="card p-3 mb-4 text-sm" style="background: var(--surface-raised); border-inline-start: 3px solid var(--color-warn-500, #d9a441)">
            <div class="flex items-center gap-2 flex-wrap">
                <x-state-badge state="warn" label="مسودّة تحرير" />
                <span class="flex-1">
                    {{ setting('courses.autosave.draft_notice', 'التعديلات المحفوظة تلقائيًّا معروضة في الفورم — اضغط «حفظ» تسري على المنشور، أو تجاهلها وترجع النسخة المنشورة.') }}
                    @if ($course->draft_saved_at)
                        <span style="color: var(--text-muted)">(آخر حفظ تلقائيّ {{ $course->draft_saved_at->format('Y-m-d H:i') }})</span>
                    @endif
                </span>
                <form method="post" action="{{ route('admin.courses.draft.discard', $course) }}">
                    @csrf @method('delete')
                    <button type="submit" class="btn rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)">تجاهل المسودّة</button>
                </form>
            </div>
            <ul class="mt-2 space-y-1" style="color: var(--text-muted)">
                @foreach ($draft as $field => $value)
                    <li>{{ $draftLabels[$field] ?? $field }}: {{ \Illuminate\Support\Str::limit((string) $value, 80) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- شريط لاصق: الحفظ بزرّين + مؤشّر الحفظ التلقائيّ (24.1) --}}
    <div class="sticky-bar card p-3 mb-4 flex items-center gap-2 flex-wrap" style="background: var(--surface-raised)">
        <span class="text-xs flex-1" style="color: var(--text-muted)" data-autosave-note>
            {{ $course->exists ? 'الحفظ التلقائيّ شغّال' : 'احفظ أوّل مرّة عشان يشتغل الحفظ التلقائيّ' }}
        </span>
        <button type="submit" form="course-form" name="continue" value="1"
                class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-sunken)">حفظ واستمرار</button>
        <button type="submit" form="course-form"
                class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">حفظ</button>
    </div>

    {{-- تابات الفورم — رقائق أفقيّة على الموبايل (2.15-ج) --}}
    <div class="flex gap-2 min-w-0 overflow-x-auto no-scrollbar mb-4" data-form-tabs>
        @foreach ($formTabs as $key => $label)
            <button type="button" data-form-tab="{{ $key }}"
                    class="shrink-0 rounded-full px-4 py-2 text-sm motion-standard"
                    style="{{ $loop->first
                        ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                        : 'background: var(--surface-raised); color: var(--text)' }}">{{ $label }}</button>
        @endforeach
    </div>

    <form method="post" action="{{ $action }}" id="course-form" data-course-form
          @if ($course->exists) data-autosave="{{ route('admin.courses.autosave', $course) }}" @endif>
        @csrf
        @if ($course->exists) @method('put') @endif

        {{-- ------------------------------------------------ تاب البيانات --}}
        <section data-form-panel="data" class="space-y-4">
            <div class="grid md:grid-cols-2 gap-3">
                <x-form.input name="name_ar" label="اسم العرض (عربيّ)" :value="$draftValue('name_ar', $course->name_ar)" required />
                <x-form.input name="name_en" label="اسم العرض (إنجليزيّ)" :value="$draftValue('name_en', $course->name_en)" />
                <x-form.input name="cert_name_ar" label="اسم الشهادة (عربيّ)" :value="$draftValue('cert_name_ar', $course->cert_name_ar)" />
                <x-form.input name="cert_name_en" label="اسم الشهادة (إنجليزيّ)" :value="$draftValue('cert_name_en', $course->cert_name_en)" />
            </div>

            <label class="block">
                <span class="block text-sm mb-1">الوصف</span>
                <textarea name="description_ar" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('description_ar', $draftValue('description_ar', $course->description_ar)) }}</textarea>
            </label>

            {{-- ⭐ التدريب يقدر يكون في أكتر من مسار (12.4-أ) --}}
            <fieldset class="card p-3">
                <legend class="text-sm px-1">المسارات (يقدر يكون في أكتر من واحد)</legend>
                <div class="grid md:grid-cols-3 gap-2 mt-2">
                    @foreach ($paths as $path)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="path_ids[]" value="{{ $path->id }}"
                                   @checked(in_array($path->id, $selectedPaths, true))>
                            <span>{{ $path->name_ar }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid md:grid-cols-2 gap-3">
                {{-- ⭐ «أيّ حقل رفع يفتح اختَر من المكتبة أو ارفع جديد» (12.4-هـ) — بلا نسخٍ ولا لصق --}}
                <div>
                    <x-form.input name="cover_path" label="الغلاف (مسار من مكتبة الوسائط)" :value="$course->cover_path"
                                  hint="افتح مكتبة الوسائط واختر صورة، أو ارفع جديدًا." />
                    <div class="flex items-center gap-2 mt-2">
                        <button type="button" data-media-pick="cover_path"
                                class="rounded-xl px-3 py-1.5 text-xs"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <x-icon name="library" size="14" /> {{ setting('media.picker.cta') }}
                        </button>
                        <span data-media-preview="cover_path" class="inline-flex items-center"></span>
                    </div>
                </div>
                <label class="block">
                    <span class="block text-sm mb-1">الحالة</span>
                    <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', $course->status) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        {{-- ------------------------------------------------ تاب التسعير --}}
        <section data-form-panel="pricing" class="space-y-4 hidden">
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_free" value="0">
                <input type="checkbox" name="is_free" value="1" @checked($course->is_free)> مجّانيّ تمامًا
            </label>

            <div class="grid md:grid-cols-3 gap-3">
                <x-form.input name="price_coins" label="السعر الأساسيّ (كوينز)" type="number" :value="(int) $draftValue('price_coins', $course->price_coins)" />
                <x-form.input name="offer_price_coins" label="سعر العرض" type="number" :value="$draftValue('offer_price_coins', $course->offer_price_coins)" />
                <x-form.input name="offer_ends_at" label="ينتهي العرض في" type="date"
                              :value="$course->offer_ends_at?->format('Y-m-d')" />
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="free_first_time" value="0">
                <input type="checkbox" name="free_first_time" value="1" @checked($course->free_first_time)> مجّانيّ أوّل مرّة
            </label>

            <details>
                <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">نصّ الـPaywall</summary>
                <label class="block mt-3">
                    <span class="block text-sm mb-1">النصّ (عربيّ)</span>
                    <textarea name="paywall_text_ar" rows="2" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('paywall_text_ar', $draftValue('paywall_text_ar', $course->paywall_text_ar)) }}</textarea>
                </label>
            </details>
        </section>

        {{-- ------------------------------------------------ تاب الإتاحة --}}
        <section data-form-panel="availability" class="space-y-4 hidden">
            <p class="text-sm" style="color: var(--text-muted)">فترات إتاحة متعدّدة + أوقات تشغيل يوميّة + ديدلاين.</p>

            @for ($i = 0; $i < (int) setting('courses.availability.max_windows', 3); $i++)
                <div class="grid md:grid-cols-2 gap-3">
                    <x-form.input :name="'availability[windows]['.$i.'][from]'" label="من تاريخ" type="date"
                                  :value="$availability['windows'][$i]['from'] ?? null" />
                    <x-form.input :name="'availability[windows]['.$i.'][to]'" label="إلى تاريخ" type="date"
                                  :value="$availability['windows'][$i]['to'] ?? null" />
                </div>
            @endfor

            <div class="grid md:grid-cols-3 gap-3">
                <x-form.input name="availability[daily_from]" label="التشغيل اليوميّ من" type="time"
                              :value="$availability['daily_from'] ?? null" />
                <x-form.input name="availability[daily_to]" label="إلى" type="time"
                              :value="$availability['daily_to'] ?? null" />
                <x-form.input name="deadline_days" label="الديدلاين (أيّام)" type="number" :value="$draftValue('deadline_days', $course->deadline_days)" />
            </div>
        </section>

        {{-- ------------------------------------------------ تاب التقييم --}}
        <section data-form-panel="grading" class="space-y-4 hidden">
            <div class="grid md:grid-cols-3 gap-3">
                {{-- ⭐ «أقصى XP للدرس» = `xp_max` وحده — وهو ما تقرؤه الحاسبة فعلًا (7) --}}
                <x-form.input name="xp_max" label="أقصى XP للدرس" type="number"
                              :value="$draftValue('xp_max', $course->xp_max ?: setting('courses.xp.max_per_lesson', 50))"
                              hint="نقطة بداية التناقص الخطّيّ — تنزل مع الوقت حتى الصفر عند الديدلاين." />
                <x-form.input name="exam_pass_score" label="درجة نجاح الامتحان" type="number"
                              :value="$exam->pass_score ?? setting('exams.pass_score.default', 70)" />
                <x-form.input name="exam_questions_count" label="عدد أسئلة الامتحان" type="number"
                              :value="$exam->questions_count ?? setting('exams.questions.default_count', 20)" />
            </div>

            {{-- تذاكر الدرس حسب نصف الديدلاين (7 · 7.1) — والفراغ معناه «اتبع الإعداد العامّ» --}}
            <div class="grid md:grid-cols-2 gap-3">
                <x-form.input name="tickets_before_half" label="تذاكر الدرس قبل نصف الديدلاين" type="number"
                              :value="$course->tickets_before_half"
                              :placeholder="'الإعداد العامّ: '.setting('tickets.before_half_deadline', 2)"
                              hint="سيبه فاضي عشان يتبع الإعداد العامّ، وحطّ صفرًا لو التدريب ده بلا تذاكر." />
                <x-form.input name="tickets_after_half" label="تذاكر الدرس بعد نصف الديدلاين" type="number"
                              :value="$course->tickets_after_half"
                              :placeholder="'الإعداد العامّ: '.setting('tickets.after_half_deadline', 1)" />
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="forced_order" value="0">
                <input type="checkbox" name="forced_order" value="1" @checked($course->forced_order)>
                ترتيب مشاهدة إجباريّ للدروس
            </label>

            @if ($indicator)
                {{-- ⭐ مؤشّر الأسئلة العامّة مقابل حدّ الامتحان (12.4-هـ) --}}
                <div class="card p-3 flex items-center gap-2">
                    <x-state-badge :state="$indicator['state']"
                                   :label="'الأسئلة العامّة '.$indicator['available'].' من '.$indicator['required']" />
                    @if ($indicator['short'] > 0)
                        <span class="text-sm">ناقصك {{ $indicator['short'] }} سؤال عامّ عشان الامتحان يتبني.</span>
                    @endif
                </div>
            @endif
        </section>

        {{-- ------------------------------------------------ تاب المحتوى --}}
        <section data-form-panel="content" class="space-y-4 hidden">
            @if (! $course->exists)
                <p class="text-sm" style="color: var(--text-muted)">احفظ التدريب الأوّل، وبعدها تبني السيكشنز والدروس.</p>
            @else
                <div class="space-y-3" data-sortable="{{ route('admin.sections.reorder', $course) }}">
                    @forelse ($sections as $section)
                        <div class="card p-4" data-sort-id="{{ $section->id }}">
                            <div class="flex items-center gap-2">
                                <span class="cursor-grab hidden md:inline" aria-hidden="true">⠿</span>
                                <div class="flex-1 font-semibold">{{ $section->title_ar }}</div>
                                <button type="button" data-sort-up class="btn md:hidden rounded-xl px-3 py-2 text-sm"
                                        style="background: var(--surface-sunken)" aria-label="فوق">↑</button>
                                <button type="button" data-sort-down class="btn md:hidden rounded-xl px-3 py-2 text-sm"
                                        style="background: var(--surface-sunken)" aria-label="تحت">↓</button>
                            </div>

                            <ul class="mt-3 space-y-2">
                                @foreach ($section->lessons as $lesson)
                                    <li class="flex items-center gap-2 text-sm">
                                        <span aria-hidden="true"><x-icon :name="$lesson->type === 'video' ? 'video' : 'document'" size="16" /></span>
                                        <a href="{{ route('admin.lessons.show', $lesson) }}" class="flex-1 underline">{{ $lesson->title_ar }}</a>
                                        @if ($lesson->questions_general > 0)
                                            <x-state-badge state="honor" :label="$lesson->questions_general.' عامّ'" />
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            <button type="button" data-modal-open="lesson-new-{{ $section->id }}"
                                    class="btn mt-3 rounded-xl px-3 py-2 text-sm"
                                    style="background: var(--surface-sunken)">+ درس</button>
                        </div>
                    @empty
                        <x-empty message="السيكشن الأوّل لسّه مستنّيك." />
                    @endforelse
                </div>

                <button type="button" data-modal-open="section-new"
                        class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">+ سيكشن</button>
            @endif
        </section>
    </form>

    @if ($course->exists)
        {{-- فورمات السيكشن والدرس خارج فورم التدريب — فورم داخل فورم ممنوع في HTML --}}
        <x-modal id="section-new" title="سيكشن جديد">
            <form method="post" action="{{ route('admin.sections.store', $course) }}" class="space-y-3">
                @csrf
                <x-form.input name="title_ar" label="اسم السيكشن" required />
                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">إضافة</button>
            </form>
        </x-modal>

        @foreach ($sections as $section)
            <x-modal :id="'lesson-new-'.$section->id" :title="'درس في: '.$section->title_ar">
                <form method="post" action="{{ route('admin.lessons.store', $section) }}" class="space-y-3">
                    @csrf
                    <x-form.input name="title_ar" label="عنوان الدرس" required />
                    <label class="block">
                        <span class="block text-sm mb-1">النوع</span>
                        <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="video">فيديو يوتيوب</option>
                            <option value="document">نصّ</option>
                        </select>
                    </label>
                    <x-form.input name="video_url" label="رابط اليوتيوب" hint="بنستخرج الـID والثامبنيل تلقائيًّا." />
                    <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">إضافة</button>
                </form>
            </x-modal>
        @endforeach
    @endif

    @include('admin.courses.partials.sortable')
    @include('admin.courses.partials.toast')
    {{-- بوب-أب «اختَر من المكتبة / ارفع جديد» لحقل الغلاف (12.4-هـ) --}}
    @include('admin.courses.partials.media-picker-modal')

    @push('scripts')
        <script>
            /* تابات الفورم: تحميل كسول — التاب لا يُعرَض إلّا عند فتحه (2.15-د) */
            document.querySelectorAll('[data-form-tab]').forEach((tab) => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('[data-form-tab]').forEach((t) => {
                        const on = t === tab;
                        t.style.background = on ? 'var(--color-brand-500)' : 'var(--surface-raised)';
                        t.style.color = on ? '#04201c' : 'var(--text)';
                        t.style.fontWeight = on ? '700' : '400';
                    });
                    document.querySelectorAll('[data-form-panel]').forEach((panel) => {
                        panel.classList.toggle('hidden', panel.dataset.formPanel !== tab.dataset.formTab);
                    });
                    /* الصفحة تفتح على آخر تاب فُتِح فيها (2.15-د) */
                    try { localStorage.setItem('hc.course.tab', tab.dataset.formTab); } catch {}
                });
            });

            try {
                const last = localStorage.getItem('hc.course.tab');
                if (last) document.querySelector(`[data-form-tab="${last}"]`)?.click();
            } catch {}

            /* ⭐ حفظ تلقائيّ كمسودّة — «اتحفظ ✓» بجوار الأزرار (12.4-ب · 2.17-ب) */
            const form = document.querySelector('[data-course-form]');
            const note = document.querySelector('[data-autosave-note]');

            if (form?.dataset.autosave) {
                let timer = null;

                form.addEventListener('input', () => {
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        const body = new FormData(form);
                        body.delete('_method');

                        fetch(form.dataset.autosave, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                Accept: 'application/json',
                            },
                            body,
                        })
                            .then((r) => r.json())
                            .then((data) => { if (note) note.textContent = `${data.message} ${data.at}`; })
                            .catch(() => { if (note) note.textContent = 'شغلك محفوظ عندك — هنحاول نحفظه تاني.'; });
                    }, {{ (int) setting('courses.autosave.debounce_ms', 2000) }});
                });
            }
        </script>
    @endpush
@endsection
