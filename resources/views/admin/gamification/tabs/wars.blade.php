@php
    use App\Services\Admin\Volunteer\WarSettingsService;

    $selected = $data['selected'];
    $shared = $data['shared'];
    $locked = $selected ? WarSettingsService::isLocked($selected) : false;
    $override = fn (string $section) => $selected ? WarSettingsService::overrideOf($selected, $section) : [];
@endphp

{{-- لافتة القفل — تُقرَأ قبل أيّ تعديل (12.10-ج) --}}
@if ($locked)
    <div class="card p-3 mb-4 text-sm" style="border-color: color-mix(in srgb, var(--color-state-danger) 45%, var(--border))">
        ◉ {{ WarSettingsService::lockMessage() }}
        <span style="color: var(--text-muted)">{{ setting('admin.gamification.tabs.wars.altadyl_la_ytbq_ala_jwla_jarya', '— التعديل لا يُطبَّق على جولة جارية.') }}</span>
    </div>
@endif

{{-- بنك أسئلة الحروب شاشة مستقلّة في نفس الدروب-داون (12.10-ب) — والرابط يُخفى بلا صلاحيّة --}}
@can('wars_bank.list')
    <div class="card p-3 mb-4 flex flex-wrap items-center gap-3 text-sm">
        <span>{{ setting('admin.gamification.tabs.wars.alasyla_nfsha_tdar_fy_bnk_asyla_alhrwb', 'الأسئلة نفسها تُدار في بنك أسئلة الحروب — والحروب لا تعمل ببنك فارغ.') }}</span>
        <a href="{{ route('admin.wars.bank.index') }}"
           class="btn ms-auto rounded-xl px-4 py-2 text-xs font-semibold motion-standard"
           style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('admin.gamification.tabs.wars.afth_bnk_alasyla', 'افتح بنك الأسئلة') }}</a>
    </div>
@endcan

{{-- تخطيط عمودين: القائمة يمينًا والتفاصيل يسارًا — وعلى الموبايل شاشة واحدة (2.15-ج) --}}
<div class="grid lg:grid-cols-3 gap-4" dir="rtl">

    {{-- يمين: بطاقة لكلّ حرب (أيقونة SVG + لون + سويتش + آخر تعديل) --}}
    <aside class="lg:col-span-1 space-y-2">
        @forelse ($data['challenges'] as $challenge)
            <a href="{{ route('admin.gamification.index', ['tab' => 'wars', 'war' => $challenge->id]) }}"
               class="card p-3 flex items-center gap-3 motion-standard hover:opacity-90"
               style="{{ $selected?->id === $challenge->id ? 'border-color: var(--color-brand-500)' : '' }}">
                <span class="shrink-0 inline-flex items-center justify-center rounded-xl"
                      style="width: 40px; height: 40px; background: {{ $challenge->color ?: 'var(--surface-sunken)' }}20; border: 1px solid var(--border)">
                    {{-- أيقونة الحرب SVG بهويّة المنصّة — ممنوع أيّ مكتبة أيقونات (2.16-ج) --}}
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                         style="color: {{ $challenge->color ?: 'var(--color-brand-500)' }}">
                        <path d="m4 20 6-6M20 4l-8 8" />
                        <path d="M14 4h6v6M4 14v6h6" />
                    </svg>
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold text-sm">{{ $challenge->name_ar }}</span>
                    <span class="block text-xs" style="color: var(--text-muted)"
                          title="{{ $challenge->updated_at?->format('Y-m-d H:i') }}">
                        {{ setting('admin.gamification.tabs.wars.akhr_tadyl', 'آخر تعديل') }} {{ $challenge->updated_at?->diffForHumans() }}
                    </span>
                </span>

                <x-state-badge :state="$challenge->is_active ? 'ok' : 'idle'"
                               :label="$challenge->is_active ? setting('admin.gamification.tabs.wars.mfala', 'مفعّلة') : setting('admin.gamification.tabs.wars.mwqwfa', 'موقوفة')" />
            </a>
        @empty
            <x-empty :message="setting('admin.gamification.tabs.wars.lm_tdf_hrwb_bad', 'لم تُضَف حروب بعد.')" />
        @endforelse
    </aside>

    {{-- يسار: تفاصيل الحرب المختارة في سكشنز مطويّة --}}
    <section class="lg:col-span-2">
        @if ($selected)
            <form method="post" action="{{ route('admin.gamification.wars.save', $selected) }}" class="card p-4 md:p-5">
                @csrf

                <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
                    <h2 class="font-bold">{{ $selected->name_ar }}</h2>
                    <label class="text-sm flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" @checked($selected->is_active) @disabled($locked)>
                        {{ setting('admin.gamification.tabs.wars.mfala', 'مفعّلة') }}
                    </label>
                </div>

                @foreach ($data['sections'] as $sectionKey => $sectionLabel)
                    <details class="mb-2 rounded-xl p-3" style="background: var(--surface-sunken)">
                        <summary class="cursor-pointer text-sm font-semibold select-none">{{ $sectionLabel }}</summary>

                        <div class="grid sm:grid-cols-2 gap-2 mt-3">
                            @php $values = $override($sectionKey); @endphp

                            @switch($sectionKey)
                                @case('costs')
                                    @foreach (['ready_tickets' => setting('admin.gamification.tabs.wars.shrt_alastadad_tdhakr', 'شرط الاستعداد (تذاكر)'), 'create_focus_tickets' => setting('admin.gamification.tabs.wars.insha_hrb_trkyz', 'إنشاء حرب تركيز'), 'join_tickets' => setting('admin.gamification.tabs.wars.alandmam', 'الانضمام')] as $key => $label)
                                        @include('admin.gamification.partials.war-field', [
                                            'section' => $sectionKey, 'key' => $key, 'label' => $label,
                                            'value' => $values[$key] ?? null, 'placeholder' => $shared[$key] ?? '', 'locked' => $locked,
                                        ])
                                    @endforeach
                                    @break

                                @case('rewards')
                                    @foreach (['win' => setting('admin.gamification.tabs.wars.fwz', 'فوز'), 'loss' => setting('admin.gamification.tabs.wars.khsara', 'خسارة'), 'withdraw' => setting('admin.gamification.tabs.wars.anshab', 'انسحاب')] as $key => $label)
                                        @include('admin.gamification.partials.war-field', [
                                            'section' => $sectionKey, 'key' => $key, 'label' => $label,
                                            'value' => $values[$key] ?? null, 'placeholder' => $shared[$key] ?? '', 'locked' => $locked,
                                        ])
                                    @endforeach
                                    @break

                                @case('timers')
                                    @foreach (['question_seconds' => setting('admin.gamification.tabs.wars.wqt_alswal_th', 'وقت السؤال (ث)'), 'decision_seconds' => setting('admin.gamification.tabs.wars.mwqt_alhsm_th', 'مؤقّت الحسم (ث)')] as $key => $label)
                                        @include('admin.gamification.partials.war-field', [
                                            'section' => $sectionKey, 'key' => $key, 'label' => $label,
                                            'value' => $values[$key] ?? null, 'placeholder' => $shared[$key] ?? '', 'locked' => $locked,
                                        ])
                                    @endforeach
                                    @include('admin.gamification.partials.war-field', [
                                        'section' => $sectionKey, 'key' => 'focus_durations', 'label' => setting('admin.gamification.tabs.wars.mdd_altrkyz_dqyqa_mfswla_bfasla', 'مدد التركيز (دقيقة، مفصولة بفاصلة)'),
                                        'value' => $values['focus_durations'] ?? null,
                                        'placeholder' => implode(',', (array) $shared['focus_durations']),
                                        'locked' => $locked, 'type' => 'text',
                                    ])
                                    @break

                                @case('question_source')
                                    @foreach (['arena_ratio' => setting('admin.gamification.tabs.wars.nsba_alsaha', 'نسبة الساحة (%)'), 'training_ratio' => setting('admin.gamification.tabs.wars.nsba_altdrybat', 'نسبة التدريبات (%)')] as $key => $label)
                                        @include('admin.gamification.partials.war-field', [
                                            'section' => $sectionKey, 'key' => $key, 'label' => $label,
                                            'value' => $values[$key] ?? null, 'placeholder' => $shared[$key] ?? '', 'locked' => $locked,
                                        ])
                                    @endforeach
                                    <label class="text-xs sm:col-span-2 flex items-center gap-2">
                                        <input type="checkbox" name="question_source[numeric_only]" value="1"
                                               @checked(! empty($values['numeric_only'])) @disabled($locked)>
                                        {{ setting('admin.gamification.tabs.wars.asyla_rqmya_fqt_astthna_hrb_altqdyr', 'أسئلة رقميّة فقط (استثناء حرب التقدير)') }}
                                    </label>
                                    @break

                                @case('limits')
                                    @foreach (['max_visible_fighters' => setting('admin.gamification.tabs.wars.aqsa_mharbyn_zahryn', 'أقصى محاربين ظاهرين'), 'max_active_focus' => setting('admin.gamification.tabs.wars.aqsa_thdyat_nshta', 'أقصى تحديات نشطة'), 'loss_rule_count' => setting('admin.gamification.tabs.wars.qaada_add_alkhsarat', 'قاعدة عدد الخسارات')] as $key => $label)
                                        @include('admin.gamification.partials.war-field', [
                                            'section' => $sectionKey, 'key' => $key, 'label' => $label,
                                            'value' => $values[$key] ?? null, 'placeholder' => $shared[$key] ?? '', 'locked' => $locked,
                                        ])
                                    @endforeach
                                    @break

                                @case('texts')
                                    @foreach (['headline' => setting('admin.gamification.tabs.wars.alhydlayn', 'الهيدلاين'), 'description' => setting('admin.gamification.tabs.wars.alwsf', 'الوصف'), 'win' => setting('admin.gamification.tabs.wars.jmla_alfwz', 'جملة الفوز'), 'lose' => setting('admin.gamification.tabs.wars.jmla_alkhsara', 'جملة الخسارة')] as $key => $label)
                                        @include('admin.gamification.partials.war-field', [
                                            'section' => $sectionKey, 'key' => $key, 'label' => $label,
                                            'value' => $values[$key] ?? null, 'placeholder' => setting('admin.gamification.tabs.wars.ytba_alns_alaam', 'يتبع النصّ العامّ'), 'locked' => $locked, 'type' => 'text',
                                        ])
                                    @endforeach
                                    <label class="text-xs sm:col-span-2">{{ setting('admin.gamification.tabs.wars.msar_ayqwna_ghlaf_alhrb_svg', 'مسار أيقونة/غلاف الحرب (SVG)') }}
                                        <input type="text" name="icon_path" value="{{ $selected->icon_path }}" @disabled($locked)
                                               class="w-full rounded-lg px-2 py-1.5 mt-1"
                                               style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                    </label>
                                    <label class="text-xs">{{ setting('admin.gamification.tabs.wars.lwn_alhrb', 'لون الحرب') }}
                                        <input type="text" name="color" value="{{ $selected->color }}" placeholder="#00d4b8" @disabled($locked)
                                               class="w-full rounded-lg px-2 py-1.5 mt-1"
                                               style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                    </label>
                                    @break
                            @endswitch
                        </div>
                    </details>
                @endforeach

                @can('wars_settings.edit')
                    <button type="submit" @disabled($locked)
                            class="btn mt-3 rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: {{ $locked ? 'var(--surface-sunken)' : 'var(--color-brand-500)' }}; color: {{ $locked ? 'var(--text-muted)' : '#04201c' }}">
                        {{ setting('admin.gamification.tabs.wars.ahfz_iadadat_alhrb', 'احفظ إعدادات الحرب') }}
                    </button>
                @endcan
            </form>

            @can('wars_settings.manage')
                <form method="post" action="{{ route('admin.gamification.wars.reset', $selected) }}" class="mt-3"
                      onsubmit="return confirm('{{ setting('admin.gamification.tabs.wars.trja_alhrb_dy_llaftrady_kl_aloverrides', 'ترجّع الحرب دي للافتراضيّ؟ كلّ الـOverrides هتتمسح.') }}')">
                    @csrf
                    <button type="submit" class="text-xs underline" style="color: var(--text-muted)"><x-icon name="refresh" size="16" /> {{ setting('admin.gamification.tabs.wars.iaada_aldbt_llaftrady_lhdhh_alhrb', 'إعادة الضبط للافتراضيّ لهذه الحرب') }}</button>
                </form>
            @endcan
        @else
            <x-empty :message="setting('admin.gamification.tabs.wars.akhtr_hrba_mn_alqayma_ashan_tzhr_tfasylha', 'اختر حربًا من القائمة عشان تظهر تفاصيلها.')" />
        @endif
    </section>
</div>

{{-- القواعد العامّة (Shared) مرّة واحدة — والقيمة العامّة تظهر Placeholder فوق --}}
@can('wars_settings.edit')
    @include('admin.volunteer.partials.settings-card', [
        'title' => setting('admin.gamification.tabs.wars.alqwaad_alaama_llhrwb_shared', 'القواعد العامّة للحروب (Shared)'),
        'rows' => $data['settings'],
        'action' => route('admin.gamification.settings.save'),
        'resetAction' => route('admin.gamification.reset'),
        'resetPayload' => ['group' => 'gamification_wars'],
    ])
@endcan
