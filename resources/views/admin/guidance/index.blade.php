@extends('layouts.admin')

@section('title', setting('admin.guidance.index.altalymat', 'التعليمات'))

@section('content')
    {{-- التعليمات: قناة بثّ إداريّة اتّجاه واحد (12.6-أ · 24.3) --}}
    <x-page-header
        :title="setting('admin.guidance.index.altalymat', 'التعليمات')"
        :subtitle="setting('admin.guidance.index.abat_mnshwra_ljmhwr_mhdd_wshwf_myn_qra_wmyn', 'ابعت منشورًا لجمهور محدَّد، وشوف مين قرأ ومين أقرّ.')"
        :breadcrumbs="[['label' => setting('admin.guidance.index.altwjyh_waldam', 'التوجيه والدعم'), 'url' => route('admin.guidance.index')], ['label' => setting('admin.guidance.index.altalymat', 'التعليمات')]]">
        <x-slot:action>
            @can('announcements.create')
                <button type="button" data-modal-open="announcement-form"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.index.mnshwr', '+ منشور') }}</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    <x-tabs :tabs="$tabs" current="announcements" />

    <x-filters :action="route('admin.guidance.index')">
        <label class="block flex-1 min-w-[12rem]">
            <span class="block text-sm mb-1">{{ setting('admin.guidance.index.bhth', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('admin.guidance.index.anwan_almnshwr', 'عنوان المنشور…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.guidance.index.alhala', 'الحالة') }}</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.guidance.index.alkl', 'الكلّ') }}</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2 text-sm mt-6">
            <input type="checkbox" name="pinned" value="1" @checked($filters['pinned'])> {{ setting('admin.guidance.index.almthbt_fqt', 'المثبَّت فقط') }}
        </label>
        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.guidance.index.tsfya', 'تصفية') }}</button>
    </x-filters>

    @if ($announcements->isEmpty())
        <x-empty :message="setting('admin.guidance.index.la_mnshwrat_abda_awl_bth', 'لا منشورات — ابدأ أوّل بثّ.')" />
    @else
        <div class="space-y-3">
            @foreach ($announcements as $announcement)
                @php $stat = $stats[$announcement->id] ?? ['reads' => 0, 'acks' => 0, 'rate' => 0]; @endphp
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <div class="font-semibold flex items-center gap-2">
                                {{ $announcement->title }}
                                @if ($announcement->is_pinned)<span title="{{ setting('admin.guidance.index.mthbt', 'مثبَّت') }}" aria-label="{{ setting('admin.guidance.index.mthbt', 'مثبَّت') }}"><x-icon name="placement" size="16" /></span>@endif
                            </div>
                            <div class="text-xs mt-1" style="color: var(--text-muted)">
                                {{ setting('admin.guidance.index.aljmhwr', 'الجمهور:') }} {{ ['all' => setting('admin.guidance.index.alkl', 'الكلّ'), 'role' => setting('admin.guidance.index.dwr', 'دور'), 'course' => setting('admin.guidance.index.tdryb', 'تدريب'), 'path' => setting('admin.guidance.index.msar', 'مسار'), 'user' => setting('admin.guidance.index.ashkhas', 'أشخاص'), 'segment' => setting('admin.guidance.index.shryha_mhfwza', 'شريحة محفوظة')][$announcement->audience['type'] ?? 'all'] ?? setting('admin.guidance.index.alkl', 'الكلّ') }}
                                {{-- القنوات المفتوحة لهذا المنشور — تُقرأ من الصفّ لا من الوعد (12.6-أ) --}}
                                {{ setting('admin.guidance.index.alqnwat', '· القنوات:') }}
                                @php
                                    $open = collect([
                                        'feed' => (bool) $announcement->show_in_feed,
                                        'push' => (bool) $announcement->push_to_notifications,
                                        'email' => (bool) $announcement->email_enabled,
                                    ])->filter()->keys()->map(fn ($key) => $channels[$key] ?? $key);
                                @endphp
                                {{ $open->isEmpty() ? setting('admin.guidance.index.mafysh', 'مافيش') : $open->implode(' · ') }}
                                @if ($announcement->email_enabled)
                                    @php $mail = $emailStats[$announcement->id] ?? []; @endphp
                                    {!! strtr(setting('admin.guidance.index.bryd_v1_atbat', '· بريد: :v1 اتبعت'), [':v1' => e((int) ($mail['sent'] ?? 0))]) !!}
                                    @if (($mail['deferred'] ?? 0) > 0) · {{ (int) $mail['deferred'] }} {{ setting('admin.guidance.index.atajlt', 'اتأجّلت') }} @endif
                                    @if (($mail['skipped'] ?? 0) > 0) · {{ (int) $mail['skipped'] }} {{ setting('admin.guidance.index.mstbad', 'مستبعَد') }} @endif
                                    @if (($mail['failed'] ?? 0) > 0) · {{ (int) $mail['failed'] }} {{ setting('admin.guidance.index.tathrt', 'تعثّرت') }} @endif
                                @endif
                                @if ($announcement->requires_acknowledge)
                                    {!! strtr(setting('admin.guidance.index.iqrar_b_v1_xp_mra_wahda', '· إقرار بـ:v1 XP (مرّة واحدة)'), [':v1' => e($announcement->acknowledge_xp)]) !!}
                                @endif
                                {{ setting('admin.guidance.index.altfaal', '· التفاعل') }} {{ $announcement->reactions_enabled ? setting('admin.guidance.index.msmwh', 'مسموح') : setting('admin.guidance.index.mmnwa', 'ممنوع') }}
                                @if ($announcement->poll_question)
                                    {{ setting('admin.guidance.index.asttlaa', '· استطلاع (') }}{{ $announcement->poll_results_public ? setting('admin.guidance.index.ntyjth_aama', 'نتيجته عامّة') : setting('admin.guidance.index.ntyjth_mkhfya', 'نتيجته مخفيّة') }})
                                @endif
                                @if ($announcement->recurrence)
                                    · {{ $frequencies[$announcement->recurrence] ?? setting('admin.guidance.index.mtkrr', 'متكرّر') }}
                                    @if ($nextRuns[$announcement->id] ?? null)
                                        {{ setting('admin.guidance.index.aldwra_aljaya', '— الدورة الجاية') }} {{ $nextRuns[$announcement->id]->diffForHumans() }}
                                    @endif
                                @endif
                                @if ($announcement->onboarding_step)
                                    {!! strtr(setting('admin.guidance.index.khtwa_v1_fy_slsla_altaryf', '· خطوة :v1 في سلسلة التعريف'), [':v1' => e($announcement->onboarding_step)]) !!}
                                @endif
                            </div>
                        </div>
                        <x-state-badge
                            :state="$announcement->status === 'published' ? 'ok' : ($announcement->status === 'archived' ? 'idle' : 'warn')"
                            :label="$statuses[$announcement->status] ?? $announcement->status" />
                    </div>

                    {{-- نسبة القراءة كشريط (24.3) --}}
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-xs">
                            <span>{{ setting('admin.guidance.index.nsba_alqraa', 'نسبة القراءة') }} {{ $stat['rate'] }}%</span>
                            <span style="color: var(--text-muted)">{{ $stat['reads'] }} {!! strtr(setting('admin.guidance.index.qraa_v1_iqrar', 'قراءة · :v1 إقرار'), [':v1' => e($stat['acks'])]) !!}</span>
                        </div>
                        <div class="h-1 rounded-full overflow-hidden mt-1" style="background: var(--surface-sunken)">
                            <div class="h-full" style="width: {{ $stat['rate'] }}%; background: var(--color-brand-500)"></div>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-3 text-xs flex-wrap">
                        <a href="{{ route('admin.guidance.analytics', $announcement) }}" class="underline">{{ setting('admin.guidance.index.thlylat', 'تحليلات') }}</a>
                        {{-- معاينة على الأجهزة قبل النشر (12.6-أ) --}}
                        <a href="{{ route('admin.guidance.preview', $announcement) }}" class="underline">{{ setting('admin.guidance.index.maayna', 'معاينة') }}</a>

                        @can('announcements.edit')
                            <form method="post" action="{{ route('admin.guidance.announcements.duplicate', $announcement) }}">
                                @csrf
                                <button class="underline">{{ setting('admin.guidance.index.nskha_jdyda', 'نسخة جديدة') }}</button>
                            </form>
                        @endcan

                        @can('announcements.archive')
                            @if ($announcement->status !== 'archived')
                                <form method="post" action="{{ route('admin.guidance.announcements.archive', $announcement) }}">
                                    @csrf
                                    <button class="underline">{{ setting('admin.guidance.index.arshfa', 'أرشفة') }}</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $announcements->links() }}</div>
    @endif

    @can('announcements.create')
        <x-modal id="announcement-form" :title="setting('admin.guidance.index.mnshwr_jdyd', 'منشور جديد')">
            <form method="post" action="{{ route('admin.guidance.announcements.store') }}" class="space-y-3">
                @csrf

                <x-form.input name="title" :label="setting('admin.guidance.index.alanwan', 'العنوان')" required />

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.guidance.index.alns', 'النصّ') }}</span>
                    <textarea name="body" rows="4" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <div class="grid md:grid-cols-2 gap-3">
                    <x-form.input name="cta_label" :label="setting('admin.guidance.index.ns_zr_cta', 'نصّ زرّ CTA')" />
                    <x-form.input name="cta_url" :label="setting('admin.guidance.index.rabt_alzr_deep_link', 'رابط الزرّ (Deep link)')" />
                </div>

                {{-- استهداف بشرائح (12.6-أ) --}}
                <fieldset class="card p-3">
                    <legend class="text-sm px-1">{{ setting('admin.guidance.index.aljmhwr_2', 'الجمهور') }}</legend>
                    <label class="block text-sm">
                        {{ setting('admin.guidance.index.alshryha', 'الشريحة') }}
                        <select name="audience_type" data-audience class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="all">{{ setting('admin.guidance.index.alkl', 'الكلّ') }}</option>
                            <option value="role">{{ setting('admin.guidance.index.hsb_aldwr', 'حسب الدور') }}</option>
                            <option value="course">{{ setting('admin.guidance.index.hsb_altdryb', 'حسب التدريب') }}</option>
                            <option value="path">{{ setting('admin.guidance.index.hsb_almsar', 'حسب المسار') }}</option>
                            <option value="user">{{ setting('admin.guidance.index.ashkhas_baynhm', 'أشخاص بعينهم') }}</option>
                            {{-- ⭐ شريحة محفوظة بدل إعادة بناء الفلاتر (12.6-أ · 12.13) --}}
                            <option value="segment">{{ setting('admin.guidance.index.shryha_mhfwza', 'شريحة محفوظة') }}</option>
                        </select>
                    </label>

                    <div class="mt-2 hidden" data-audience-panel="segment">
                        <select name="audience_ids[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($audiences['segments'] as $segment)
                                <option value="{{ $segment->id }}">
                                    {{ $segment->name }} — {{ \App\Services\Admin\AudienceSegments::types()[$segment->segment_type] ?? $segment->segment_type }}
                                    ({{ number_format((int) $segment->size) }})
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ setting('announcements.audience.segment_hint', 'الشريحة بتتحلّ لأعضائها على السيرفر لحظة الإرسال — مش لحظة الحفظ.') }}
                        </p>
                    </div>
                    @if ($audiences['segments']->isEmpty())
                        <p class="text-xs mt-1" style="color: var(--text-muted)">
                            <a href="{{ route('admin.users.segments') }}" class="underline">{{ setting('announcements.audience.segments_empty', 'مفيش شرائح محفوظة لسّه — ابنِ واحدة') }}</a>
                        </p>
                    @endif

                    <div class="mt-2 hidden" data-audience-panel="role">
                        <select name="audience_keys[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($audiences['roles'] as $role)
                                <option value="{{ $role->key }}">{{ $role->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-2 hidden" data-audience-panel="course">
                        <select name="audience_ids[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($audiences['courses'] as $course)
                                <option value="{{ $course->id }}">{{ $course->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-2 hidden" data-audience-panel="path">
                        <select name="audience_ids[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($audiences['paths'] as $path)
                                <option value="{{ $path->id }}">{{ $path->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>
                </fieldset>

                {{-- السلوك: التفاعل والإقرار بـXP بسقف مرّة لكلّ منشور (12.6-أ) --}}
                <fieldset class="card p-3 space-y-2 text-sm">
                    <legend class="text-sm px-1">{{ setting('admin.guidance.index.alslwk', 'السلوك') }}</legend>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="reactions_enabled" value="1"> {{ setting('admin.guidance.index.asmh_baltfaal_iymwjy', 'اسمح بالتفاعل (إيموجي)') }}
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="requires_acknowledge" value="1"> {{ setting('admin.guidance.index.ilzam_iqrar_qrat_wfhmt', 'إلزام إقرار «قرأتُ وفهمت»') }}
                    </label>
                    <div class="grid md:grid-cols-2 gap-3">
                        <x-form.input name="acknowledge_xp" :label="setting('admin.guidance.index.xp_aliqrar_mra_wahda_lkl_mnshwr', 'XP الإقرار (مرّة واحدة لكلّ منشور)')" type="number"
                                      :value="setting('announcements.acknowledge.default_xp', 0)" />
                        <x-form.input name="acknowledge_tickets" :label="setting('admin.guidance.index.tdhakr_aliqrar', 'تذاكر الإقرار')" type="number"
                                      :value="setting('announcements.acknowledge.default_tickets', 0)" />
                    </div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_pinned" value="1"> {{ setting('admin.guidance.index.thbth_aala_alqnaa', 'ثبّته أعلى القناة') }}
                    </label>
                </fieldset>

                {{-- ⭐ القنوات الموحّدة من مكان واحد (12.6-أ): تاب · Toast/إشعار · بريد،
                     وكلّ قناة مستقلّة — تقدر تبعت بالبريد وحده بلا ما يظهر في الفيد --}}
                <fieldset class="card p-3 space-y-2 text-sm">
                    <legend class="text-sm px-1 flex items-center gap-1">
                        <x-icon name="announcement" size="16" /> {{ setting('admin.guidance.index.alqnwat_2', 'القنوات') }}
                    </legend>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="show_in_feed" value="1"
                               @checked(setting('announcements.channels.feed_default_on', true))>
                        {{ $channels['feed'] ?? setting('admin.guidance.index.tab_altalymat', 'تاب التعليمات') }}
                    </label>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="push_to_notifications" value="1">
                        {{ $channels['push'] ?? setting('admin.guidance.index.ishaar_toast', 'إشعار / Toast') }}
                    </label>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="email_enabled" value="1">
                        <span class="flex items-center gap-1"><x-icon name="envelope" size="16" /> {{ $channels['email'] ?? setting('admin.guidance.index.bryd', 'بريد') }}</span>
                    </label>

                    <p class="text-xs" style="color: var(--text-muted)">
                        {{ setting('announcements.email.editor_hint', 'البريد بيروح لمن بريده موثَّق ومفعّل القناة بس — والزيادة بتتأجّل احترامًا لحدّ الهدوء.') }}
                    </p>
                </fieldset>

                {{-- ⭐ استطلاع داخل المنشور: عامّ النتيجة أو مخفيّها (12.6-أ) —
                     وبناؤه صلاحيّة مستقلّة، ومن لا يملكها لا يرى الحقول أصلًا (2.15-أ-7) --}}
                @can('announcement_polls.create')
                <details class="card p-3">
                    <summary class="text-sm cursor-pointer">{{ setting('admin.guidance.index.asttlaa_dakhl_almnshwr_akhtyary', 'استطلاع داخل المنشور (اختياريّ)') }}</summary>
                    <div class="mt-3 space-y-2">
                        <x-form.input name="poll_question" :label="setting('admin.guidance.index.swal_alasttlaa', 'سؤال الاستطلاع')" />

                        @for ($i = 0; $i < (int) setting('announcements.poll.max_options', 6); $i++)
                            <label class="block">
                                <span class="block text-sm mb-1" for="poll-option-{{ $i }}">{{ setting('admin.guidance.index.khyar', 'خيار') }} {{ $i + 1 }}</span>
                                <input type="text" name="poll_options[]" id="poll-option-{{ $i }}"
                                       value="{{ old('poll_options.'.$i) }}" maxlength="120"
                                       class="w-full rounded-xl px-3 py-2 text-sm"
                                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            </label>
                        @endfor

                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="poll_results_public" value="1"
                                   @checked(setting('announcements.poll.results_public_default', false))>
                            {{ setting('admin.guidance.index.alntyja_aama_yshwfha_alkl', 'النتيجة عامّة يشوفها الكلّ') }}
                        </label>
                        <p class="text-xs" style="color: var(--text-muted)">
                            {{ setting('admin.guidance.index.lw_sbtha_mqfwla_alntyja_msh_htwsl_mtsfh', 'لو سِبتها مقفولة، النتيجة **مش هتوصل متصفّح المستخدم أصلًا** لحدّ ما الاستطلاع يقفل — إخفاء حقيقيّ لا شكليّ.') }}
                        </p>

                        <x-form.input name="poll_closes_at" :label="setting('admin.guidance.index.yqfl_alasttlaa_fy', 'يقفل الاستطلاع في')" type="datetime-local" />
                    </div>
                </details>
                @endcan

                {{-- ⭐ جدولة متكرّرة + سلسلة Onboarding متدرّجة (12.6-أ) --}}
                <details class="card p-3">
                    <summary class="text-sm cursor-pointer">{{ setting('admin.guidance.index.tkrar_wslsla_taryf_akhtyary', 'تكرار وسلسلة تعريف (اختياريّ)') }}</summary>
                    <div class="mt-3 grid md:grid-cols-2 gap-3">
                        <label class="block">
                            <span class="block text-sm mb-1">{{ setting('admin.guidance.index.altkrar', 'التكرار') }}</span>
                            <select name="recurrence" class="w-full rounded-xl px-3 py-2 text-sm"
                                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                <option value="">{{ setting('admin.guidance.index.bla_tkrar', 'بلا تكرار') }}</option>
                                @foreach ($frequencies as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <x-form.input name="recurrence_until" :label="setting('admin.guidance.index.ytkrr_hta', 'يتكرّر حتّى')" type="datetime-local" />
                        <x-form.input name="onboarding_step" :label="setting('admin.guidance.index.trtybh_fy_slsla_alonboarding', 'ترتيبه في سلسلة الـOnboarding')" type="number" />
                        <x-form.input name="onboarding_delay_days" :label="setting('admin.guidance.index.yzhr_bad_kam_ywm_mn_altsjyl', 'يظهر بعد كام يوم من التسجيل')" type="number" value="0" />
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-muted)">
                        {{ setting('admin.guidance.index.qalb_altkrar_nfsh_ma_bytbatsh_kl_dwra_bttla', 'قالب التكرار نفسه ما بيتبعتش — كلّ دورة بتطلع كمنشور جديد، فالقراءة والإقرار يتجدّدوا. وخطوة السلسلة ما بتظهرش غير لمّا اللي قبلها تتقري.') }}
                    </p>
                </details>

                {{-- التخصيص الديناميكيّ: وسوم تُكتَب في النصّ (12.6-أ) --}}
                <div class="text-xs card p-3" style="color: var(--text-muted)">
                    {{ setting('admin.guidance.index.wswm_altkhsys', 'وسوم التخصيص:') }}
                    @foreach ($tokens as $token => $meaning)
                        <code>{{ $token }}</code>@if (! $loop->last) · @endif
                    @endforeach
                </div>

                <div class="grid md:grid-cols-3 gap-3">
                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('admin.guidance.index.alhala', 'الحالة') }}</span>
                        <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-form.input name="scheduled_at" :label="setting('admin.guidance.index.ynshr_fy', 'ينشر في')" type="datetime-local" />
                    <x-form.input name="expires_at" :label="setting('admin.guidance.index.ywrshf_fy', 'يُؤرشَف في')" type="datetime-local" />
                </div>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.index.hfz_almnshwr', 'حفظ المنشور') }}</button>
            </form>
        </x-modal>
    @endcan

    @include('admin.courses.partials.toast')

    @push('scripts')
        <script>
            /* شرائح الجمهور: نعرض حقل الشريحة المختارة فقط (2.15 — إخفاء التعقيد) */
            const audience = document.querySelector('[data-audience]');
            audience?.addEventListener('change', () => {
                document.querySelectorAll('[data-audience-panel]').forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.audiencePanel !== audience.value);
                });
            });
        </script>
    @endpush
@endsection

@section('mobile_action')
    @can('announcements.create')
        <button type="button" data-modal-open="announcement-form"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.index.mnshwr', '+ منشور') }}</button>
    @endcan
@endsection
