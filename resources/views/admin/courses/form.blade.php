@extends('layouts.admin')

@section('title', $course->exists ? setting('admin.courses.form.tadyl_tdryb', 'تعديل تدريب') : setting('admin.courses.form.tdryb_jdyd', 'تدريب جديد'))

@section('content')
    @php
        $action = $course->exists ? route('admin.courses.update', $course) : route('admin.courses.store');
        $formTabs = [
            'data' => setting('admin.courses.form.albyanat', 'البيانات'),
            'pricing' => setting('admin.courses.form.altsayr', 'التسعير'),
            'availability' => setting('admin.courses.form.alitaha', 'الإتاحة'),
            'grading' => setting('admin.courses.form.altqyym', 'التقييم'),
            'content' => setting('admin.courses.form.almhtwa', 'المحتوى'),
        ];

        /* ⭐ مسوّدة التحرير المعلّقة **تُعاد إلى الحقول** (12.4-ب): الحفظ التلقائيّ
           على تدريبٍ حيّ لا يمسّ المنشور، فلو لم ترجع هنا لضاع عمل المحرّر لحظة
           ضغطه «حفظ» — لأنّ الفورم كان سيرسل قيم النسخة المنشورة فوقها. */
        $draft = $pendingDraft ?? [];
        $draftValue = fn (string $field, $fallback = null) => $draft[$field] ?? $fallback;
        $draftLabels = [
            'name_ar' => setting('admin.courses.form.asm_alard_arby', 'اسم العرض (عربيّ)'),
            'name_en' => setting('admin.courses.form.asm_alard_injlyzy', 'اسم العرض (إنجليزيّ)'),
            'cert_name_ar' => setting('admin.courses.form.asm_alshhada_arby', 'اسم الشهادة (عربيّ)'),
            'cert_name_en' => setting('admin.courses.form.asm_alshhada_injlyzy', 'اسم الشهادة (إنجليزيّ)'),
            'description_ar' => setting('admin.courses.form.alwsf_arby', 'الوصف (عربيّ)'),
            'description_en' => setting('admin.courses.form.alwsf_injlyzy', 'الوصف (إنجليزيّ)'),
            'price_coins' => setting('admin.courses.form.alsar_alasasy', 'السعر الأساسيّ'),
            'offer_price_coins' => setting('admin.courses.form.sar_alard', 'سعر العرض'),
            'paywall_text_ar' => setting('admin.courses.form.ns_alpaywall_arby', 'نصّ الـPaywall (عربيّ)'),
            'paywall_text_en' => setting('admin.courses.form.ns_alpaywall_injlyzy', 'نصّ الـPaywall (إنجليزيّ)'),
            'deadline_days' => setting('admin.courses.form.aldydlayn_ayam', 'الديدلاين (أيّام)'),
            'xp_max' => setting('admin.courses.form.aqsa_xp_lldrs', 'أقصى XP للدرس'),
        ];
    @endphp

    <x-page-header
        :title="$course->exists ? $course->name_ar : setting('admin.courses.form.tdryb_jdyd', 'تدريب جديد')"
        :subtitle="setting('admin.courses.form.amla_altabat_ala_mhlk_bnhfz_mswda_tlqayya', 'املأ التابات على مهلك — بنحفظ مسودّة تلقائيًّا فما بيضيعش شغلك.')"
        :breadcrumbs="[
            ['label' => setting('admin.courses.form.altdrybat', 'التدريبات'), 'url' => route('admin.courses.index')],
            ['label' => $course->exists ? $course->name_ar : setting('admin.courses.form.jdyd', 'جديد')],
        ]" />

    {{-- ⭐ مسوّدة تحرير معلّقة على تدريبٍ حيّ (12.4-ب): الحفظ التلقائيّ لا يمسّ
         المنشور، فشغلك محفوظ هنا ومعروض في الحقول حتى تختار «حفظ» فيسري على
         الناس، أو «تجاهل المسودّة» فترجع النسخة المنشورة كما هي. --}}
    @if (! empty($draft))
        <div class="card p-3 mb-4 text-sm" style="background: var(--surface-raised); border-inline-start: 3px solid var(--color-warn-500, #d9a441)">
            <div class="flex items-center gap-2 flex-wrap">
                <x-state-badge state="warn" :label="setting('admin.courses.form.mswda_thryr', 'مسودّة تحرير')" />
                <span class="flex-1">
                    {{ setting('courses.autosave.draft_notice', 'التعديلات المحفوظة تلقائيًّا معروضة في الفورم — اضغط «حفظ» تسري على المنشور، أو تجاهلها وترجع النسخة المنشورة.') }}
                    @if ($course->draft_saved_at)
                        <span style="color: var(--text-muted)">{{ setting('admin.courses.form.akhr_hfz_tlqayy', '(آخر حفظ تلقائيّ') }} {{ $course->draft_saved_at->format('Y-m-d H:i') }})</span>
                    @endif
                </span>
                <form method="post" action="{{ route('admin.courses.draft.discard', $course) }}">
                    @csrf @method('delete')
                    <button type="submit" class="btn rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken)">{{ setting('admin.courses.form.tjahl_almswda', 'تجاهل المسودّة') }}</button>
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
            {{ $course->exists ? setting('admin.courses.form.alhfz_altlqayy_shghal', 'الحفظ التلقائيّ شغّال') : setting('admin.courses.form.ahfz_awl_mra_ashan_yshtghl_alhfz_altlqayy', 'احفظ أوّل مرّة عشان يشتغل الحفظ التلقائيّ') }}
        </span>
        <button type="submit" form="course-form" name="continue" value="1"
                class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-sunken)">{{ setting('admin.courses.form.hfz_wastmrar', 'حفظ واستمرار') }}</button>
        <button type="submit" form="course-form"
                class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.form.hfz', 'حفظ') }}</button>
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
                <x-form.input name="name_ar" :label="setting('admin.courses.form.asm_alard_arby', 'اسم العرض (عربيّ)')" :value="$draftValue('name_ar', $course->name_ar)" required />
                <x-form.input name="name_en" :label="setting('admin.courses.form.asm_alard_injlyzy', 'اسم العرض (إنجليزيّ)')" :value="$draftValue('name_en', $course->name_en)" />
                <x-form.input name="cert_name_ar" :label="setting('admin.courses.form.asm_alshhada_arby', 'اسم الشهادة (عربيّ)')" :value="$draftValue('cert_name_ar', $course->cert_name_ar)" />
                <x-form.input name="cert_name_en" :label="setting('admin.courses.form.asm_alshhada_injlyzy', 'اسم الشهادة (إنجليزيّ)')" :value="$draftValue('cert_name_en', $course->cert_name_en)" />
            </div>

            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.courses.form.alwsf', 'الوصف') }}</span>
                <textarea name="description_ar" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('description_ar', $draftValue('description_ar', $course->description_ar)) }}</textarea>
            </label>

            {{-- ⭐ التدريب يقدر يكون في أكتر من مسار (12.4-أ) --}}
            <fieldset class="card p-3">
                <legend class="text-sm px-1">{{ setting('admin.courses.form.almsarat_yqdr_ykwn_fy_aktr_mn_wahd', 'المسارات (يقدر يكون في أكتر من واحد)') }}</legend>
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
                    <x-form.input name="cover_path" :label="setting('admin.courses.form.alghlaf_msar_mn_mktba_alwsayt', 'الغلاف (مسار من مكتبة الوسائط)')" :value="$course->cover_path"
                                  :hint="setting('admin.courses.form.afth_mktba_alwsayt_wakhtr_swra_aw_arfa_jdyda', 'افتح مكتبة الوسائط واختر صورة، أو ارفع جديدًا.')" />
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
                    <span class="block text-sm mb-1">{{ setting('admin.courses.form.alhala', 'الحالة') }}</span>
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
                <input type="checkbox" name="is_free" value="1" @checked($course->is_free)> {{ setting('admin.courses.form.mjany_tmama', 'مجّانيّ تمامًا') }}
            </label>

            <div class="grid md:grid-cols-3 gap-3">
                <x-form.input name="price_coins" :label="setting('admin.courses.form.alsar_alasasy_kwynz', 'السعر الأساسيّ (كوينز)')" type="number" :value="(int) $draftValue('price_coins', $course->price_coins)" />
                <x-form.input name="offer_price_coins" :label="setting('admin.courses.form.sar_alard', 'سعر العرض')" type="number" :value="$draftValue('offer_price_coins', $course->offer_price_coins)" />
                <x-form.input name="offer_ends_at" :label="setting('admin.courses.form.ynthy_alard_fy', 'ينتهي العرض في')" type="date"
                              :value="$course->offer_ends_at?->format('Y-m-d')" />
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="free_first_time" value="0">
                <input type="checkbox" name="free_first_time" value="1" @checked($course->free_first_time)> {{ setting('admin.courses.form.mjany_awl_mra', 'مجّانيّ أوّل مرّة') }}
            </label>

            <details>
                <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">{{ setting('admin.courses.form.ns_alpaywall', 'نصّ الـPaywall') }}</summary>
                <label class="block mt-3">
                    <span class="block text-sm mb-1">{{ setting('admin.courses.form.alns_arby', 'النصّ (عربيّ)') }}</span>
                    <textarea name="paywall_text_ar" rows="2" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('paywall_text_ar', $draftValue('paywall_text_ar', $course->paywall_text_ar)) }}</textarea>
                </label>
            </details>
        </section>

        {{-- ------------------------------------------------ تاب الإتاحة --}}
        <section data-form-panel="availability" class="space-y-4 hidden">
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.courses.form.ftrat_itaha_mtadda_awqat_tshghyl_ywmya', 'فترات إتاحة متعدّدة + أوقات تشغيل يوميّة + ديدلاين.') }}</p>

            @for ($i = 0; $i < (int) setting('courses.availability.max_windows', 3); $i++)
                <div class="grid md:grid-cols-2 gap-3">
                    <x-form.input :name="'availability[windows]['.$i.'][from]'" :label="setting('admin.courses.form.mn_tarykh', 'من تاريخ')" type="date"
                                  :value="$availability['windows'][$i]['from'] ?? null" />
                    <x-form.input :name="'availability[windows]['.$i.'][to]'" :label="setting('admin.courses.form.ila_tarykh', 'إلى تاريخ')" type="date"
                                  :value="$availability['windows'][$i]['to'] ?? null" />
                </div>
            @endfor

            <div class="grid md:grid-cols-3 gap-3">
                <x-form.input name="availability[daily_from]" :label="setting('admin.courses.form.altshghyl_alywmy_mn', 'التشغيل اليوميّ من')" type="time"
                              :value="$availability['daily_from'] ?? null" />
                <x-form.input name="availability[daily_to]" :label="setting('admin.courses.form.ila', 'إلى')" type="time"
                              :value="$availability['daily_to'] ?? null" />
                <x-form.input name="deadline_days" :label="setting('admin.courses.form.aldydlayn_ayam', 'الديدلاين (أيّام)')" type="number" :value="$draftValue('deadline_days', $course->deadline_days)" />
            </div>
        </section>

        {{-- ------------------------------------------------ تاب التقييم --}}
        <section data-form-panel="grading" class="space-y-4 hidden">
            <div class="grid md:grid-cols-3 gap-3">
                {{-- ⭐ «أقصى XP للدرس» = `xp_max` وحده — وهو ما تقرؤه الحاسبة فعلًا (7) --}}
                <x-form.input name="xp_max" :label="setting('admin.courses.form.aqsa_xp_lldrs', 'أقصى XP للدرس')" type="number"
                              :value="$draftValue('xp_max', $course->xp_max ?: setting('courses.xp.max_per_lesson', 50))"
                              :hint="setting('admin.courses.form.nqta_bdaya_altnaqs_alkhty_tnzl_ma_alwqt_hta', 'نقطة بداية التناقص الخطّيّ — تنزل مع الوقت حتى الصفر عند الديدلاين.')" />
                <x-form.input name="exam_pass_score" :label="setting('admin.courses.form.drja_njah_alamthan', 'درجة نجاح الامتحان')" type="number"
                              :value="$exam->pass_score ?? setting('exams.pass_score.default', 70)" />
                <x-form.input name="exam_questions_count" :label="setting('admin.courses.form.add_asyla_alamthan', 'عدد أسئلة الامتحان')" type="number"
                              :value="$exam->questions_count ?? setting('exams.questions.default_count', 20)" />
            </div>

            {{-- تذاكر الدرس حسب نصف الديدلاين (7 · 7.1) — والفراغ معناه «اتبع الإعداد العامّ» --}}
            <div class="grid md:grid-cols-2 gap-3">
                <x-form.input name="tickets_before_half" :label="setting('admin.courses.form.tdhakr_aldrs_qbl_nsf_aldydlayn', 'تذاكر الدرس قبل نصف الديدلاين')" type="number"
                              :value="$course->tickets_before_half"
                              :placeholder="setting('admin.courses.form.aliadad_alaam', 'الإعداد العامّ: ').setting('tickets.before_half_deadline', 2)"
                              :hint="setting('admin.courses.form.sybh_fady_ashan_ytba_aliadad_alaam_wht_sfra', 'سيبه فاضي عشان يتبع الإعداد العامّ، وحطّ صفرًا لو التدريب ده بلا تذاكر.')" />
                <x-form.input name="tickets_after_half" :label="setting('admin.courses.form.tdhakr_aldrs_bad_nsf_aldydlayn', 'تذاكر الدرس بعد نصف الديدلاين')" type="number"
                              :value="$course->tickets_after_half"
                              :placeholder="setting('admin.courses.form.aliadad_alaam', 'الإعداد العامّ: ').setting('tickets.after_half_deadline', 1)" />
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="forced_order" value="0">
                <input type="checkbox" name="forced_order" value="1" @checked($course->forced_order)>
                {{ setting('admin.courses.form.trtyb_mshahda_ijbary_lldrws', 'ترتيب مشاهدة إجباريّ للدروس') }}
            </label>

            @if ($indicator)
                {{-- ⭐ مؤشّر الأسئلة العامّة مقابل حدّ الامتحان (12.4-هـ) --}}
                <div class="card p-3 flex items-center gap-2">
                    <x-state-badge :state="$indicator['state']"
                                   :label="setting('admin.courses.form.alasyla_alaama', 'الأسئلة العامّة ').$indicator['available'].setting('admin.courses.form.mn', ' من ').$indicator['required']" />
                    @if ($indicator['short'] > 0)
                        <span class="text-sm">{!! strtr(setting('admin.courses.form.naqsk_v1_swal_aam_ashan_alamthan_ytbny', 'ناقصك :v1 سؤال عامّ عشان الامتحان يتبني.'), [':v1' => e($indicator['short'])]) !!}</span>
                    @endif
                </div>
            @endif
        </section>

        {{-- ------------------------------------------------ تاب المحتوى --}}
        <section data-form-panel="content" class="space-y-4 hidden">
            @if (! $course->exists)
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.courses.form.ahfz_altdryb_alawl_wbadha_tbny_alsykshnz', 'احفظ التدريب الأوّل، وبعدها تبني السيكشنز والدروس.') }}</p>
            @else
                <div class="space-y-3" data-sortable="{{ route('admin.sections.reorder', $course) }}">
                    @forelse ($sections as $section)
                        <div class="card p-4" data-sort-id="{{ $section->id }}">
                            <div class="flex items-center gap-2">
                                <span class="cursor-grab hidden md:inline" aria-hidden="true">⠿</span>
                                <div class="flex-1 font-semibold">{{ $section->title_ar }}</div>
                                <button type="button" data-sort-up class="btn md:hidden rounded-xl px-3 py-2 text-sm"
                                        style="background: var(--surface-sunken)" aria-label="{{ setting('admin.courses.form.fwq', 'فوق') }}">↑</button>
                                <button type="button" data-sort-down class="btn md:hidden rounded-xl px-3 py-2 text-sm"
                                        style="background: var(--surface-sunken)" aria-label="{{ setting('admin.courses.form.tht', 'تحت') }}">↓</button>
                            </div>

                            <ul class="mt-3 space-y-2">
                                @foreach ($section->lessons as $lesson)
                                    <li class="flex items-center gap-2 text-sm">
                                        <span aria-hidden="true"><x-icon :name="$lesson->type === 'video' ? 'video' : 'document'" size="16" /></span>
                                        <a href="{{ route('admin.lessons.show', $lesson) }}" class="flex-1 underline">{{ $lesson->title_ar }}</a>
                                        @if ($lesson->questions_general > 0)
                                            <x-state-badge state="honor" :label="$lesson->questions_general.setting('admin.courses.form.aam', ' عامّ')" />
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            <button type="button" data-modal-open="lesson-new-{{ $section->id }}"
                                    class="btn mt-3 rounded-xl px-3 py-2 text-sm"
                                    style="background: var(--surface-sunken)">{{ setting('admin.courses.form.drs', '+ درس') }}</button>
                        </div>
                    @empty
                        <x-empty :message="setting('admin.courses.form.alsykshn_alawl_lsh_mstnyk', 'السيكشن الأوّل لسّه مستنّيك.')" />
                    @endforelse
                </div>

                <button type="button" data-modal-open="section-new"
                        class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.courses.form.sykshn', '+ سيكشن') }}</button>
            @endif
        </section>
    </form>

    @if ($course->exists)
        {{-- فورمات السيكشن والدرس خارج فورم التدريب — فورم داخل فورم ممنوع في HTML --}}
        <x-modal id="section-new" :title="setting('admin.courses.form.sykshn_jdyd', 'سيكشن جديد')">
            <form method="post" action="{{ route('admin.sections.store', $course) }}" class="space-y-3">
                @csrf
                <x-form.input name="title_ar" :label="setting('admin.courses.form.asm_alsykshn', 'اسم السيكشن')" required />
                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.form.idafa', 'إضافة') }}</button>
            </form>
        </x-modal>

        @foreach ($sections as $section)
            <x-modal :id="'lesson-new-'.$section->id" :title="setting('admin.courses.form.drs_fy', 'درس في: ').$section->title_ar">
                <form method="post" action="{{ route('admin.lessons.store', $section) }}" class="space-y-3">
                    @csrf
                    <x-form.input name="title_ar" :label="setting('admin.courses.form.anwan_aldrs', 'عنوان الدرس')" required />
                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('admin.courses.form.alnwa', 'النوع') }}</span>
                        <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="video">{{ setting('admin.courses.form.fydyw_ywtywb', 'فيديو يوتيوب') }}</option>
                            <option value="document">{{ setting('admin.courses.form.ns', 'نصّ') }}</option>
                        </select>
                    </label>
                    <x-form.input name="video_url" :label="setting('admin.courses.form.rabt_alywtywb', 'رابط اليوتيوب')" :hint="setting('admin.courses.form.bnstkhrj_alid_walthambnyl_tlqayya', 'بنستخرج الـID والثامبنيل تلقائيًّا.')" />
                    <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.form.idafa', 'إضافة') }}</button>
                </form>
            </x-modal>
        @endforeach
    @endif

    @include('admin.courses.partials.sortable')
    @include('admin.courses.partials.toast')
    {{-- بوب-أب «اختَر من المكتبة / ارفع جديد» لحقل الغلاف (12.4-هـ) --}}
    @include('admin.courses.partials.media-picker-modal')

    @push('scripts')
        @php
            /*
             | نصوص السكربت من الإعدادات (2.13-أ): لا حرفَ عربيّ داخل `<script>`،
             | فالمحروق هناك لا يصل لوحةَ الإدارة ولا الترجمة.
             */
            $jsText = [
                'autosave_failed' => setting('admin.courses.form.shghlk_mhfwz_andk_hnhawl_nhfzh_tany', 'شغلك محفوظ عندك — هنحاول نحفظه تاني.'),
            ];
        @endphp

        <script>
            const HC_COURSE_FORM_TEXT = @json($jsText);
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
                            .catch(() => { if (note) note.textContent = HC_COURSE_FORM_TEXT.autosave_failed; });
                    }, {{ (int) setting('courses.autosave.debounce_ms', 2000) }});
                });
            }
        </script>
    @endpush
@endsection
