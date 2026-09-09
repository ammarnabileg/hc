@extends('layouts.admin')

@section('title', setting('admin.courses.lesson.aldrs', 'الدرس: ').$lesson->title_ar)

@section('content')
    {{-- بناء الدرس (12.4-ج): فيديو/كود/مرفقات أو نصّ + تبويب أسئلة --}}
    <x-page-header
        :title="$lesson->title_ar"
        :subtitle="setting('admin.courses.lesson.aldrs_fydyw_ywtywb_bkwd_wmrfqat_aw_ns_mnsq', 'الدرس فيديو يوتيوب بكود ومرفقات، أو نصّ منسّق — وتحته أسئلته.')"
        :breadcrumbs="[
            ['label' => setting('admin.courses.lesson.altdrybat', 'التدريبات'), 'url' => route('admin.courses.index')],
            ['label' => $course->name_ar, 'url' => route('admin.courses.edit', $course)],
            ['label' => $section->title_ar],
            ['label' => $lesson->title_ar],
        ]" />

    <div class="grid lg:grid-cols-2 gap-4">
        {{-- ------------------------------------------------ محتوى الدرس --}}
        <section class="card p-4 space-y-3">
            <h2 class="font-bold">{{ setting('admin.courses.lesson.mhtwa_aldrs', 'محتوى الدرس') }}</h2>

            <form method="post" action="{{ route('admin.lessons.update', $lesson) }}" class="space-y-3">
                @csrf @method('put')

                <x-form.input name="title_ar" :label="setting('admin.courses.lesson.alanwan', 'العنوان')" :value="$lesson->title_ar" required />

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.courses.lesson.alnwa', 'النوع') }}</span>
                    <select name="type" class="w-full rounded-xl px-3 py-2 text-sm" data-lesson-type
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="video" @selected($lesson->type === 'video')>{{ setting('admin.courses.lesson.fydyw_ywtywb', 'فيديو يوتيوب') }}</option>
                        <option value="document" @selected($lesson->type === 'document')>{{ setting('admin.courses.lesson.ns', 'نصّ') }}</option>
                    </select>
                </label>

                <div data-lesson-video class="space-y-3 {{ $lesson->type === 'video' ? '' : 'hidden' }}">
                    <x-form.input name="video_url" :label="setting('admin.courses.lesson.rabt_alywtywb', 'رابط اليوتيوب')" :value="$lesson->video_id"
                                  :hint="setting('admin.courses.lesson.alid_walthambnyl_bytstkhrjwa_tlqayya', 'الـID والثامبنيل بيتستخرجوا تلقائيًّا.')" />
                    @if ($lesson->video_id)
                        <img src="https://img.youtube.com/vi/{{ $lesson->video_id }}/mqdefault.jpg"
                             alt="{{ setting('admin.courses.lesson.maayna_alfydyw', 'معاينة الفيديو') }}" loading="lazy" class="rounded-xl max-w-full">
                    @endif
                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('admin.courses.lesson.kwd_html_taba', 'كود/HTML تابع') }}</span>
                        <textarea name="embed_html" rows="3" class="w-full rounded-xl px-3 py-2 text-sm font-mono"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('embed_html', $lesson->embed_html) }}</textarea>
                    </label>
                </div>

                <label class="block" data-lesson-text>
                    <span class="block text-sm mb-1">{{ setting('admin.courses.lesson.alns', 'النصّ') }}</span>
                    <textarea name="content" rows="6" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('content', $lesson->content) }}</textarea>
                </label>

                {{--
                    المرفقات من المكتبة المركزيّة — يترفع مرّة ويُعاد استخدامه (12.4-د).

                    ⚠️ **لماذا لم تبقَ قائمة Checkbox؟** لأنّها كانت محدودةً بـ
                    `media.picker.limit` (12 عنصرًا) **وبلا بحث** — فالملفّ الثالث
                    عشر في المكتبة **لا يمكن إرفاقه أصلًا**. والدستور يوجب أن يفتح
                    أيّ حقل رفعٍ **بوب-أب المكتبة** (12.4-هـ)، وللمكتبة **بحثٌ
                    بالاسم** (12.4-د) — فالحلّ هو نفس البوب-أب في وضعه المتعدّد،
                    لا قائمةٌ أطول.
                --}}
                <fieldset class="card p-3">
                    <legend class="text-sm px-1">{{ setting('admin.courses.lesson.mrfqat_mn_mktba_alwsayt', 'مرفقات من مكتبة الوسائط') }}</legend>

                    {{--
                        ⚠️ **علَم «الفورم يدير المرفقات»**: إن شال الأدمن آخر مرفق
                        فلن يُرسَل `attachment_ids` أصلًا (فورم HTML لا يرسل مصفوفةً
                        فارغة)، فيقرأ الخادم غيابًا لا تفريغًا ويبقى المرفق ملتصقًا
                        رغم إزالته. فالعلَم يفرّق بين **«لم يُرسَل»** و**«فُرِّغ»**.
                    --}}
                    <input type="hidden" name="attachments_managed" value="1">

                    {{-- المرفقات الحاليّة رقائق، ولكلٍّ حقلٌ مخفيّ يحمل آيدي عنصر المكتبة --}}
                    <div class="flex flex-wrap gap-2 mt-2" data-media-multi="attachment_ids">
                        <span class="text-xs {{ $attachments->isEmpty() ? '' : 'hidden' }}" data-multi-empty
                              style="color: var(--text-muted)">{{ setting('media.picker.attachments_empty', 'مافيش مرفقات لسه.') }}</span>
                        @foreach ($attachments as $row)
                            @continue (! $row->media_item)
                            <span class="inline-flex items-center gap-2 rounded-xl px-3 py-1.5 text-xs"
                                  data-multi-id="{{ $row->media_item_id }}"
                                  style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border)">
                                <input type="hidden" name="attachment_ids[]" value="{{ $row->media_item_id }}">
                                <span class="truncate" style="max-width: 12rem">{{ $row->media_item->name }}</span>
                                <button type="button" data-multi-remove style="min-width: 44px; min-height: 44px"
                                        aria-label="{{ setting('media.picker.remove') }}">✕</button>
                            </span>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        <button type="button" data-media-pick-multiple="attachment_ids"
                                class="rounded-xl px-3 py-1.5 text-xs"
                                style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <x-icon name="library" size="14" /> {{ setting('media.picker.multi_cta') }}
                        </button>
                        <a href="{{ route('admin.media.index') }}" class="text-xs underline">{{ setting('admin.courses.lesson.afth_almktba', 'افتح المكتبة') }}</a>
                    </div>
                </fieldset>

                <div class="grid md:grid-cols-2 gap-3">
                    <x-form.input name="duration_minutes" :label="setting('admin.courses.lesson.almda_dqayq', 'المدّة (دقائق)')" type="number" :value="$lesson->duration_minutes" />
                    <label class="flex items-center gap-2 text-sm mt-6">
                        <input type="hidden" name="is_free_preview" value="0">
                        <input type="checkbox" name="is_free_preview" value="1" @checked($lesson->is_free_preview)> {{ setting('admin.courses.lesson.maayna_mjanya', 'معاينة مجّانيّة') }}
                    </label>
                </div>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.lesson.hfz_aldrs', 'حفظ الدرس') }}</button>
            </form>

            {{-- نقل الدرس بين السيكشنز + تكرار (12.4-هـ) --}}
            <details>
                <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">{{ setting('admin.courses.lesson.nql_aw_tkrar', 'نقل أو تكرار') }}</summary>
                <div class="mt-3 space-y-3">
                    <form method="post" action="{{ route('admin.lessons.move', $lesson) }}" class="flex gap-2">
                        @csrf
                        <select name="section_id" class="flex-1 rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($sections as $target)
                                <option value="{{ $target->id }}" @selected($target->id === $section->id)>{{ $target->title_ar }}</option>
                            @endforeach
                        </select>
                        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.courses.lesson.anql', 'انقل') }}</button>
                    </form>

                    <form method="post" action="{{ route('admin.lessons.duplicate', $lesson) }}">
                        @csrf
                        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.courses.lesson.tkrar_aldrs', 'تكرار الدرس') }}</button>
                    </form>
                </div>
            </details>
        </section>

        {{-- ------------------------------------------------ تبويب الأسئلة --}}
        <section class="card p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-bold">{{ setting('admin.courses.lesson.asyla_aldrs', 'أسئلة الدرس') }}</h2>
                <button type="button" data-modal-open="csv-import" class="text-xs underline">{{ setting('admin.courses.lesson.astyrad_csv', 'استيراد CSV') }}</button>
            </div>

            @if (session('import_errors'))
                <div class="card p-3 text-sm" style="border-color: var(--color-state-danger)">
                    <div class="font-semibold mb-1">{{ setting('admin.courses.lesson.sfwf_mhtaja_mrajaa', 'صفوف محتاجة مراجعة:') }}</div>
                    <ul class="space-y-1">
                        @foreach (session('import_errors') as $error)
                            <li>{{ setting('admin.courses.lesson.sf', 'صفّ') }} {{ $error['row'] }}: {{ $error['message'] }}</li>
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
                                {{ ['otp' => setting('admin.courses.lesson.rqmy_otp', 'رقميّ OTP'), 'choice' => setting('admin.courses.lesson.akhtyar_mtadd', 'اختيار متعدّد'), 'text' => setting('admin.courses.lesson.nsy', 'نصّيّ')][$question->type] ?? $question->type }}
                                · Placeholder: {{ $question->placeholder ?: '—' }}
                            </div>
                        </div>
                        @if ($question->is_general)
                            <x-state-badge state="honor" :label="setting('admin.courses.lesson.swal_aam', 'سؤال عامّ')" />
                        @endif
                    </div>
                    <div class="flex gap-2 mt-2">
                        <form method="post" action="{{ route('admin.questions.general', $question) }}">
                            @csrf
                            <button class="text-xs underline">{{ $question->is_general ? setting('admin.courses.lesson.shylh_mn_albnk', 'شيله من البنك') : setting('admin.courses.lesson.ajalh_aama', 'اجعله عامًّا') }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.questions.destroy', $question) }}"
                              onsubmit="return confirm('{{ setting('admin.courses.lesson.nshyl_alswal', 'نشيل السؤال؟') }}')">
                            @csrf @method('delete')
                            <button class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('admin.courses.lesson.hdhf', 'حذف') }}</button>
                        </form>
                    </div>
                </div>
            @empty
                <x-empty :message="setting('admin.courses.lesson.mfysh_asyla_lsh_dyf_awl_swal', 'مفيش أسئلة لسّه — ضيف أوّل سؤال.')" />
            @endforelse

            <form method="post" action="{{ route('admin.questions.store', $lesson) }}" class="space-y-3 card p-3">
                @csrf
                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.courses.lesson.alnwa', 'النوع') }}</span>
                    <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="otp">{{ setting('admin.courses.lesson.rqmy_otp', 'رقميّ OTP') }}</option>
                        <option value="choice">{{ setting('admin.courses.lesson.akhtyar_mtadd', 'اختيار متعدّد') }}</option>
                        <option value="text">{{ setting('admin.courses.lesson.nsy', 'نصّيّ') }}</option>
                    </select>
                </label>

                <x-form.input name="prompt" :label="setting('admin.courses.lesson.ns_alswal', 'نصّ السؤال')" required />
                {{-- ⭐ نصّ Placeholder داخل الحقل (12.4-ج) --}}
                <x-form.input name="placeholder" :label="setting('admin.courses.lesson.ns_dakhl_alhql_placeholder', 'نصّ داخل الحقل (Placeholder)')"
                              :placeholder="setting('lessons.questions.placeholder_otp', 'اكتب الرقم')" />

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.courses.lesson.alkhyarat_str_lkl_khyar', 'الخيارات (سطر لكلّ خيار)') }}</span>
                    <textarea name="options" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <x-form.input name="correct_answer" :label="setting('admin.courses.lesson.alijaba_alshyha', 'الإجابة الصحيحة')" />

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_general" value="1"> {{ setting('admin.courses.lesson.swal_aam_ydkhl_bnk_alamthan_alnhayy', 'سؤال عامّ (يدخل بنك الامتحان النهائيّ)') }}
                </label>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.lesson.adf_alswal', 'أضِف السؤال') }}</button>
            </form>
        </section>
    </div>

    <x-modal id="csv-import" :title="setting('admin.courses.lesson.astyrad_asyla_csv', 'استيراد أسئلة CSV')">
        <form method="post" action="{{ route('admin.questions.import', $lesson) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <p class="text-sm" style="color: var(--text-muted)">
                {!! strtr(setting('admin.courses.lesson.aamda_almlf_v1_walkhyarat_tfsl_balama', 'أعمدة الملفّ: :v1 — والخيارات تُفصَل بعلامة |'), [':v1' => e(implode(' · ', $csvColumns))]) !!}
            </p>
            <input type="file" name="file" accept=".csv,text/csv" required class="w-full text-sm">
            <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.lesson.astwrd', 'استورد') }}</button>
        </form>
    </x-modal>

    @include('admin.courses.partials.toast')

    {{-- بوب-أب المكتبة نفسه — مصدرٌ واحد لكلّ حقول الرفع (12.4-هـ · 2.14-ب) --}}
    @include('admin.courses.partials.media-picker-modal')

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
