{{-- XP والتذاكر (12.10 · 7 · 7.1): مصادر الكسب وأوجه الصرف وقيمتا التدريب --}}

<section class="card p-4 md:p-5">
    <h2 class="font-bold mb-1">{{ setting('admin.gamification.tabs.xp.msadr_ksb_xp_waltdhakr', 'مصادر كسب XP والتذاكر') }}</h2>
    <p class="text-xs mb-3" style="color: var(--text-muted)">{{ setting('admin.gamification.tabs.xp.alqyma_alhd_alywmy_altfayl_walsf_alfargh', 'القيمة · الحدّ اليوميّ · التفعيل — والصفّ الفارغ يُهمَل عند الحفظ.') }}</p>

    <form method="post" action="{{ route('admin.gamification.xp.rows.save') }}">
        @csrf
        <input type="hidden" name="key" value="xp_rules.earn">

        <div class="space-y-2">
            @foreach (array_values($data['earn']) as $i => $row)
                @php $consumed = array_key_exists($row['key'] ?? '', $data['earnConsumed'] ?? []); @endphp
                <div class="rounded-xl p-3 grid grid-cols-2 md:grid-cols-5 gap-2 items-end" style="background: var(--surface-sunken)">
                    {{-- ⭐ الصفّ الذي لا يقرؤه الكود يُقال فيه ذلك صراحةً — لا إعداد بلا أثر (2.13) --}}
                    @unless ($consumed)
                        <p class="col-span-2 md:col-span-5 text-xs -mt-1 mb-1 inline-flex items-start gap-1" style="color: var(--color-state-warn)">
                            <x-icon name="warning" size="13" />
                            <span>{{ setting('admin.gamification.tabs.xp.almftah_dh_malwsh_msthlk_fy_alkwd_tadylh_msh', 'المفتاح ده مالوش مستهلك في الكود — تعديله مش هيغيّر حاجة. مكافآت النادي في تاب «الستريك» (سلّم الحضور)، ومكافأة الدعوة تذكرة مش XP.') }}</span>
                        </p>
                    @endunless
                    <label class="text-xs">{{ setting('admin.gamification.tabs.xp.almsdr', 'المصدر') }}
                        <input type="text" name="rows[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">{{ setting('admin.gamification.tabs.xp.almftah', 'المفتاح') }}
                        <input type="text" name="rows[{{ $i }}][key]" value="{{ $row['key'] ?? '' }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1 font-mono" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">{{ setting('admin.gamification.tabs.xp.alqyma', 'القيمة') }}
                        <input type="number" step="any" name="rows[{{ $i }}][value]" value="{{ $row['value'] ?? 0 }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">{{ setting('admin.gamification.tabs.xp.hd_ywmy_bla_hd', 'حدّ يوميّ (0 = بلا حدّ)') }}
                        <input type="number" min="0" name="rows[{{ $i }}][daily_cap]" value="{{ $row['daily_cap'] ?? 0 }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">{{ setting('admin.gamification.tabs.xp.alhala', 'الحالة') }}
                        <select name="rows[{{ $i }}][enabled]" class="w-full rounded-lg px-2 py-1.5 mt-1"
                                style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                            <option value="1" @selected($row['enabled'] ?? true)>{{ setting('admin.gamification.tabs.xp.mfal', 'مفعَّل') }}</option>
                            <option value="0" @selected(! ($row['enabled'] ?? true))>{{ setting('admin.gamification.tabs.xp.mwqwf', 'موقوف') }}</option>
                        </select>
                    </label>
                </div>
            @endforeach

            {{-- صفّ فارغ للإضافة — بلا شاشة منفصلة (2.15-أ-6) --}}
            @php $next = count($data['earn']); @endphp
            <div class="rounded-xl p-3 grid grid-cols-2 md:grid-cols-5 gap-2 items-end" style="background: var(--surface-sunken)">
                <label class="text-xs">{{ setting('admin.gamification.tabs.xp.msdr_jdyd', 'مصدر جديد') }}
                    <input type="text" name="rows[{{ $next }}][label]" placeholder="{{ setting('admin.gamification.tabs.xp.asm_almsdr', 'اسم المصدر') }}"
                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="text-xs">{{ setting('admin.gamification.tabs.xp.almftah', 'المفتاح') }}
                    <input type="text" name="rows[{{ $next }}][key]" placeholder="source.key"
                           class="w-full rounded-lg px-2 py-1.5 mt-1 font-mono" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="text-xs">{{ setting('admin.gamification.tabs.xp.alqyma', 'القيمة') }}
                    <input type="number" step="any" name="rows[{{ $next }}][value]" value="0"
                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="text-xs">{{ setting('admin.gamification.tabs.xp.hd_ywmy', 'حدّ يوميّ') }}
                    <input type="number" min="0" name="rows[{{ $next }}][daily_cap]" value="0"
                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="text-xs">{{ setting('admin.gamification.tabs.xp.alhala', 'الحالة') }}
                    <select name="rows[{{ $next }}][enabled]" class="w-full rounded-lg px-2 py-1.5 mt-1"
                            style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                        <option value="1">{{ setting('admin.gamification.tabs.xp.mfal', 'مفعَّل') }}</option>
                        <option value="0">{{ setting('admin.gamification.tabs.xp.mwqwf', 'موقوف') }}</option>
                    </select>
                </label>
            </div>
        </div>

        @can('xp_rules.edit')
            <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.gamification.tabs.xp.ahfz_msadr_alksb', 'احفظ مصادر الكسب') }}</button>
        @endcan
    </form>
</section>

<section class="card p-4 md:p-5 mt-4">
    <h2 class="font-bold mb-1">{{ setting('admin.gamification.tabs.xp.mwada_alsrf', 'مواضع الصرف') }}</h2>
    <p class="text-xs mb-3" style="color: var(--text-muted)">{{ setting('admin.gamification.tabs.xp.alwjh_alamla_altklfa_lhza_alkhsm', 'الوجه · العملة · التكلفة · لحظة الخصم.') }}</p>

    <form method="post" action="{{ route('admin.gamification.xp.rows.save') }}">
        @csrf
        <input type="hidden" name="key" value="xp_rules.spend">

        <div class="space-y-2">
            @foreach (array_values($data['spend']) as $i => $row)
                <div class="rounded-xl p-3 grid grid-cols-2 md:grid-cols-5 gap-2 items-end" style="background: var(--surface-sunken)">
                    <label class="text-xs">{{ setting('admin.gamification.tabs.xp.alwjh', 'الوجه') }}
                        <input type="text" name="rows[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">{{ setting('admin.gamification.tabs.xp.almftah', 'المفتاح') }}
                        <input type="text" name="rows[{{ $i }}][key]" value="{{ $row['key'] ?? '' }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1 font-mono" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">{{ setting('admin.gamification.tabs.xp.alamla', 'العملة') }}
                        <input type="text" name="rows[{{ $i }}][currency]" value="{{ $row['currency'] ?? 'tickets' }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">{{ setting('admin.gamification.tabs.xp.altklfa', 'التكلفة') }}
                        <input type="number" step="any" name="rows[{{ $i }}][cost]" value="{{ $row['cost'] ?? 0 }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-xs">{{ setting('admin.gamification.tabs.xp.lhza_alkhsm', 'لحظة الخصم') }}
                        <input type="text" name="rows[{{ $i }}][moment]" value="{{ $row['moment'] ?? '' }}"
                               class="w-full rounded-lg px-2 py-1.5 mt-1 font-mono" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>
            @endforeach
        </div>

        @can('xp_rules.edit')
            <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.gamification.tabs.xp.ahfz_mwada_alsrf', 'احفظ مواضع الصرف') }}</button>
        @endcan
    </form>
</section>

{{-- ⭐ قيمتا التدريب قبل/بعد نصف المهلة --}}
<section class="card p-4 md:p-5 mt-4">
    <h2 class="font-bold mb-1">{{ setting('admin.gamification.tabs.xp.tdhakr_itmam_altdryb', 'تذاكر إتمام التدريب') }}</h2>
    <p class="text-sm" style="color: var(--text-muted)">
        {{ setting('admin.gamification.tabs.xp.qbl_nsf_almhla', 'قبل نصف المهلة:') }} <strong>{{ setting('tickets.before_half_deadline', 2) }}</strong> {{ setting('admin.gamification.tabs.xp.tdhkra_badha_whta_aldydlayn', 'تذكرة · بعدها وحتى الديدلاين:') }} <strong>{{ setting('tickets.after_half_deadline', 1) }}</strong> {{ setting('admin.gamification.tabs.xp.tdhkra_nqta_almntsf', 'تذكرة · نقطة المنتصف:') }} <strong>{{ setting('tickets.midpoint_percent', 50) }}%</strong> {{ setting('admin.gamification.tabs.xp.mn_almhla', 'من المهلة.') }}
    </p>
    <p class="text-xs mt-2" style="color: var(--text-muted)">{{ setting('admin.gamification.tabs.xp.tadl_mn_blwk_aliadadat_tht', 'تُعدَّل من بلوك الإعدادات تحت.') }}</p>
</section>

{{--
  ⭐ المستويات وعتبات XP — قسمٌ في هذه الصفحة لا وجهةً في خريطة 12.0: العتبة
  رقم XP فموضعُها اقتصاد XP. وكانت تابًّا لا يذكره السايد بار ولا الخريطة،
  فما كان يُبلَغ إلّا بكتابة `?tab=levels` بالعنوان. وصلاحيّاته كما هي
  (`achievements.edit` للتعديل · `achievements.manage` للحذف).
--}}
<div class="mt-4">
    @include('admin.gamification.tabs.levels')
</div>

@can('xp_rules.edit')
    @include('admin.volunteer.partials.settings-card', [
        'title' => setting('admin.gamification.tabs.xp.iadadat_xp_waltdhakr', 'إعدادات XP والتذاكر'),
        'rows' => $data['settings'],
        'action' => route('admin.gamification.settings.save'),
        'resetAction' => route('admin.gamification.reset'),
        'resetPayload' => ['group' => 'gamification_xp'],
        'lockedKeys' => ['xp_rules.course_xp_once_locked'],
        'open' => true,
    ])
@endcan
