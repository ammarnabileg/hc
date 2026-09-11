{{-- معاينة الامتحان النهائيّ **كما سيُبنى** لهذا التدريب (12.4-هـ) — لا «معاينة كطالب» ولا معاينة البنك العامّة (24.1-3) --}}
@extends('layouts.admin')

@section('title', setting('admin.courses.exam_preview.maayna_alamthan_alnhayy', 'معاينة الامتحان النهائيّ'))

@php
    /**
     * ⭐ **معاينة الامتحان النهائيّ كما سيُبنى** لهذا التدريب (12.4-هـ).
     *
     * ليست «المعاينة كطالب» (تلك محتوى التدريب في `preview.blade.php`)، وليست
     * معاينة البنك العامّة (24.1-3) التي تجيب عن «هل بنك المنصّة يكفي؟». هذه
     * تجيب عن سؤالٍ واحد: **«امتحان هذا التدريب — بقواعده المحفوظة — هيتبني
     * إزّاي لو اتبنى دلوقتي؟»**
     *
     * والأسئلة المعروضة تأتي من نفس الميثود التي تقرؤها شاشة الامتحان
     * (`QuestionBank::builtQuestions()`) — فما تراه هنا هو ما يمتحنه المتدرّب.
     */
    $types = $preview['types'];
    $difficulties = $preview['difficulties'];

    $sourceLabels = [
        'own' => setting('admin.courses.exam_preview.mn_drws_hdha_altdryb', 'من دروس هذا التدريب'),
        'other' => setting('admin.courses.exam_preview.aam_mn_tdryb_akhr', 'عامّ من تدريب آخر'),
        'manual' => setting('admin.courses.exam_preview.mktwb_fy_alamthan_mbashra', 'مكتوب في الامتحان مباشرةً'),
    ];
@endphp

@section('content')
    <x-page-header
        :title="setting('admin.courses.exam_preview.maayna_alamthan_alnhayy_2', 'معاينة الامتحان النهائيّ: ').$course->name_ar"
        :subtitle="setting('admin.courses.exam_preview.dh_alamthan_kma_hytbna_lhdha_altdryb_bqwaadh', 'ده الامتحان كما هيتبني لهذا التدريب بقواعده المحفوظة — نفس الترتيب والعدد اللي هيشوفهم المتدرّب.')"
        :breadcrumbs="[
            ['label' => setting('admin.courses.exam_preview.altdrybat', 'التدريبات'), 'url' => route('admin.courses.index')],
            ['label' => $course->name_ar, 'url' => route('admin.courses.edit', $course)],
            ['label' => setting('admin.courses.exam_preview.maayna_alamthan_alnhayy', 'معاينة الامتحان النهائيّ')],
        ]">
        <x-slot:action>
            <a href="{{ route('admin.courses.edit', $course) }}"
               class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.courses.exam_preview.rjwa_lltdryb', 'رجوع للتدريب') }}</a>
        </x-slot:action>
    </x-page-header>

    @if ($preview['state'] === 'unconfigured')
        {{-- ⛔ بلا امتحانٍ محفوظ: إرشادٌ يقول **الخطوة التالية** لا شاشةٌ مكسورة (2.15-د · 2.17-ج) --}}
        <x-empty :message="setting('admin.courses.exam_preview.lssh_mafysh_amthan_mhfwz_lltdryb_dh_afth_tab', 'لسّه مافيش امتحان محفوظ للتدريب ده — افتح تاب «التقييم» وحدّد درجة النجاح وعدد الأسئلة واحفظ، وهتلاقي المعاينة هنا.')"
                 :action="setting('admin.courses.exam_preview.rwh_ltab_altqyym', 'روح لتاب التقييم')"
                 :href="route('admin.courses.edit', $course)" />
    @else
        {{-- القواعد المحفوظة — ومنها يُبنى كلّ ما تحت (12.4-ب: تاب التقييم) --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <x-kpi :label="setting('admin.courses.exam_preview.add_asyla_alamthan', 'عدد أسئلة الامتحان')" :value="$preview['required']" icon="document" />
            <x-kpi :label="setting('admin.courses.exam_preview.drja_alnjah', 'درجة النجاح')" :value="$preview['exam']->pass_score" icon="chart" />
            <x-kpi :label="setting('admin.courses.exam_preview.almda_baldqayq', 'المدّة (بالدقائق)')" :value="$preview['exam']->duration_minutes" icon="clock" />
            <x-kpi :label="setting('admin.courses.exam_preview.almdmwm_llamthan', 'المضموم للامتحان')" :value="$preview['attached']" icon="training"
                   :hint="setting('admin.courses.exam_preview.hytqdm_mnha', 'هيتقدّم منها ').$preview['questions']->count()" />
        </div>

        {{-- حالة البناء بسطر واحد: يتبني كامل؟ ولا ناقص كام؟ --}}
        <div class="card p-4 mt-4">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-state-badge :state="$preview['state']"
                               :label="$preview['questions']->count().setting('admin.courses.exam_preview.swal_mn', ' سؤال من ').$preview['required']" />
                <x-state-badge :state="$indicator['state']"
                               :label="setting('admin.courses.exam_preview.alasyla_alaama', 'الأسئلة العامّة ').$indicator['available'].setting('admin.courses.exam_preview.mn', ' من ').$indicator['required']" />
                @if ($preview['shortfall'] > 0)
                    <span>{!! strtr(setting('admin.courses.exam_preview.naqs_v1_swal_alamthan_hytbny_naqsa_kda', 'ناقص :v1 سؤال — الامتحان هيتبني ناقصًا كده.'), [':v1' => e($preview['shortfall'])]) !!}</span>
                @else
                    <span style="color: var(--text-muted)">{{ setting('admin.courses.exam_preview.alamthan_ytbny_kamla_baladd_almtlwb', 'الامتحان بيتبني كاملًا بالعدد المطلوب.') }}</span>
                @endif
            </div>
            <p class="text-xs mt-2" style="color: var(--text-muted)">
                {{ setting('admin.courses.exam_preview.altrtyb_hna_hw_trtyb_alamthan_nfsh_sort_order', 'الترتيب هنا هو ترتيب الامتحان نفسه (sort_order ثمّ الرقم) — بلا خلط، زيّ ما المتدرّب هيشوفه بالظبط.') }}
            </p>
        </div>

        {{-- ⭐ التوزيع: النوع · الصعوبة · المصدر — الأرقام دي هي «كما سيُبنى» مختصرةً --}}
        <div class="grid md:grid-cols-3 gap-3 mt-4">
            <div class="card p-4">
                <h2 class="font-bold mb-2 text-sm">{{ setting('admin.courses.exam_preview.tawzya_alanwaa', 'توزيع الأنواع') }}</h2>
                @forelse ($preview['by_type'] as $key => $count)
                    <div class="flex items-center justify-between text-sm py-1" style="border-top: 1px solid var(--border)">
                        <span>{{ $types[$key] ?? $key }}</span>
                        <span class="font-semibold">{{ $count }}</span>
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.courses.exam_preview.mafysh_asyla_lsa', 'مافيش أسئلة لسّه.') }}</p>
                @endforelse
            </div>

            <div class="card p-4">
                <h2 class="font-bold mb-2 text-sm">{{ setting('admin.courses.exam_preview.tawzya_alswaba', 'توزيع الصعوبة') }}</h2>
                @forelse ($preview['by_difficulty'] as $key => $count)
                    <div class="flex items-center justify-between text-sm py-1" style="border-top: 1px solid var(--border)">
                        <span>{{ $difficulties[$key] ?? setting('admin.courses.exam_preview.ghyr_mhdda', 'غير محدَّدة') }}</span>
                        <span class="font-semibold">{{ $count }}</span>
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.courses.exam_preview.mafysh_asyla_lsa', 'مافيش أسئلة لسّه.') }}</p>
                @endforelse
            </div>

            <div class="card p-4">
                <h2 class="font-bold mb-2 text-sm">{{ setting('admin.courses.exam_preview.tawzya_almsdr', 'توزيع المصدر') }}</h2>
                @forelse ($preview['by_source'] as $key => $count)
                    <div class="flex items-center justify-between text-sm py-1" style="border-top: 1px solid var(--border)">
                        <span>{{ $sourceLabels[$key] ?? $key }}</span>
                        <span class="font-semibold">{{ $count }}</span>
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.courses.exam_preview.mafysh_asyla_lsa', 'مافيش أسئلة لسّه.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- الأسئلة كما ستُقدَّم — بنفس عرض معاينة البنك (24.1-3) فالشاشتان تُقرآن بعينٍ واحدة --}}
        <h2 class="font-bold mt-6 mb-2">{{ setting('admin.courses.exam_preview.alasyla_kma_stqdm', 'الأسئلة كما ستُقدَّم') }}</h2>

        @if ($preview['questions']->isEmpty())
            {{-- ⭐ وصلة البنك **تُخفى** لمن لا يملك مفتاحها لا تُعطَّل (2.15-أ-7) — والرسالة تبقى --}}
            @canany(['question_bank.list', 'question_bank.view'])
                <x-empty :message="setting('admin.courses.exam_preview.mafysh_swal_wahd_mdmwm_lamthan_altdryb_dh', 'مافيش سؤال واحد مضموم لامتحان التدريب ده — ضُمّ أسئلة عامّة من بنك الأسئلة عشان الامتحان يتبني.')"
                         :action="setting('admin.courses.exam_preview.rwh_lbnk_alasyla', 'روح لبنك الأسئلة')"
                         :href="route('admin.question-bank.index', ['course' => $course->id, 'general' => '1'])" />
            @else
                <x-empty :message="setting('admin.courses.exam_preview.mafysh_swal_wahd_mdmwm_lamthan_altdryb_dh', 'مافيش سؤال واحد مضموم لامتحان التدريب ده — ضُمّ أسئلة عامّة من بنك الأسئلة عشان الامتحان يتبني.')" />
            @endcanany
        @else
            <ol class="space-y-3">
                @foreach ($preview['questions'] as $index => $question)
                    <li class="card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-semibold">{{ $index + 1 }}. {{ $question->prompt }}</p>
                            <span class="text-xs shrink-0" style="color: var(--text-muted)">
                                {{ $types[$question->type] ?? $question->type }}
                            </span>
                        </div>

                        @if (is_array($question->options) && $question->options !== [])
                            <ul class="mt-3 space-y-1 text-sm">
                                @foreach ($question->options as $option)
                                    <li class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">{{ $option }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        {{-- ⭐ المرشّحون لسدّ النقص: أسئلة هذا التدريب العامّة النشطة غير المضمومة.
             الأدمن لا يحتاج تخمين «منين أجيب الناقص؟» — الجواب معروضٌ بالاسم. --}}
        @if ($preview['shortfall'] > 0)
            <h2 class="font-bold mt-6 mb-2">{{ setting('admin.courses.exam_preview.almrshhwn_lsd_alnqs', 'المرشّحون لسدّ النقص') }}</h2>

            @if ($preview['candidates']->isEmpty())
                @canany(['question_bank.list', 'question_bank.view'])
                    <x-empty :message="setting('admin.courses.exam_preview.mafysh_asyla_aama_nshta_fy_altdryb_dh_tsd', 'مافيش أسئلة عامّة نشطة في التدريب ده تسدّ النقص — علّم أسئلة دروسه بـ«سؤال عام» الأوّل.')"
                             :action="setting('admin.courses.exam_preview.rwh_lbnk_alasyla', 'روح لبنك الأسئلة')"
                             :href="route('admin.question-bank.index', ['course' => $course->id])" />
                @else
                    <x-empty :message="setting('admin.courses.exam_preview.mafysh_asyla_aama_nshta_fy_altdryb_dh_tsd', 'مافيش أسئلة عامّة نشطة في التدريب ده تسدّ النقص — علّم أسئلة دروسه بـ«سؤال عام» الأوّل.')" />
                @endcanany
            @else
                <div class="card overflow-hidden">
                    <table class="w-full text-sm">
                        <thead style="background: var(--surface-sunken)">
                            <tr class="text-start">
                                <th class="p-3 text-start">{{ setting('admin.courses.exam_preview.alswal', 'السؤال') }}</th>
                                <th class="p-3 text-start">{{ setting('admin.courses.exam_preview.aldrs', 'الدرس') }}</th>
                                <th class="p-3 text-start">{{ setting('admin.courses.exam_preview.alnwa', 'النوع') }}</th>
                                <th class="p-3 text-start">{{ setting('admin.courses.exam_preview.alswaba', 'الصعوبة') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($preview['candidates'] as $candidate)
                                <tr style="border-top: 1px solid var(--border)">
                                    <td class="p-3">{{ $candidate->prompt }}</td>
                                    <td class="p-3">{{ $candidate->lesson_title }}</td>
                                    <td class="p-3">{{ $types[$candidate->type] ?? $candidate->type }}</td>
                                    <td class="p-3">{{ $difficulties[$candidate->difficulty] ?? setting('admin.courses.exam_preview.ghyr_mhdda', 'غير محدَّدة') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    @endif
@endsection
