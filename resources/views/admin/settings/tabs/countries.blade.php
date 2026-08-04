@php
    /**
     * 12.7-د «بيانات الدول» — العنوان يَعِد بثلاثة فيوفيها: **مصدر** · **فحص
     * فروق قبل الدمج (مضاف/محذوف/معدَّل)** · **دمج بلا فقد بيانات**.
     *
     * والقاعدة المعروضة للمالك صراحةً (2.11-د · قاعدة المالك):
     * لا حذف إطلاقًا · المحافظة لا تُخفى أبدًا · وما له مستخدم لا يُمَسّ.
     */
    $filters = $countries['filters'];
    $rows = $countries['rows'];
    $snapshot = $countries['snapshot'];
    $diff = $countries['diff'];
    $report = session('countries_report');
    $attribution = (string) setting('countries.attribution', 'بيانات الدول والمحافظات من dr5hn/countries-states-cities-database — برخصة ODbL v1.0.');
    $sourceUrl = trim((string) setting('countries.source_url', ''));

    // آخر فحص للمصدر — نجح أو فشل، وسببه مكتوب. لا يمرّ عبر
    // `SettingsAdminController` لأنّ التاب يُحمَّل كسولًا وهذه بيانات التاب وحده.
    $sync = app(\App\Services\Admin\System\CountryDataSync::class);
    $lastCheck = $sync->lastCheck();
@endphp

<div class="card p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="text-sm font-extrabold">{{ setting('admin.settings.tabs.countries.byanat_aldwl', 'بيانات الدول') }}</div>
            <p class="text-xs mt-1" style="color: var(--text-muted)">
                {!! strtr(setting('admin.settings.tabs.countries.hdth_almsdr_v1_bad_ma_tshwf_alfrwq_bnfsk_wma', 'حدّث المصدر (:v1) بعد ما تشوف الفروق بنفسك — وما حدش بيفقد ارتباطه بدولته ولا بمحافظته.'), [':v1' => e(setting('countries.source', 'dr5hn'))]) !!}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @can('countries_data.export')
                <a href="{{ route('admin.countries.export') }}" class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised)">{{ setting('admin.settings.tabs.countries.tsdyr_alnskha_alhalya', 'تصدير النسخة الحاليّة') }}</a>
            @endcan

            @can('countries_data.import')
                {{-- جلب من الشبكة ⟵ فروق ⟵ وقوف: لا دمج آليّ، القرار للمالك (12.7-د) --}}
                <form method="post" action="{{ route('admin.countries.check-source') }}">
                    @csrf
                    <button class="rounded-xl px-3 py-2 text-sm inline-flex items-center gap-2"
                            style="background: var(--surface-raised)">
                        {{-- أيقونة SVG مرسومة بهويّة المنصّة (كرة أرضيّة + قوس تحديث) — بلا أيّ مكتبة أيقونات (2.16-ج) --}}
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="shrink-0"
                             style="color: var(--color-brand-500)">
                            <circle cx="8" cy="8" r="5.2" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M2.8 8h10.4M8 2.8c1.5 1.6 1.5 8.8 0 10.4-1.5-1.6-1.5-8.8 0-10.4Z"
                                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M13.4 3.4v2.4h-2.4" stroke="currentColor" stroke-width="1.5"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        {{ setting('countries.source.check.button', 'فحص المصدر الآن') }}
                    </button>
                </form>

                <form method="post" action="{{ route('admin.countries.check') }}">
                    @csrf
                    <button class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.settings.tabs.countries.fhs_althdythat', 'فحص التحديثات') }}</button>
                </form>

                {{-- الفعل الرئيسيّ الوحيد في الشاشة (2.15-أ-2) --}}
                <form method="post" action="{{ route('admin.countries.import') }}" enctype="multipart/form-data"
                      class="flex items-center gap-1">
                    @csrf
                    <input type="file" name="file" accept="application/json" required class="text-xs w-40"
                           aria-label="{{ setting('admin.settings.tabs.countries.mlf_nskha_almsdr_json', 'ملفّ نسخة المصدر (JSON)') }}">
                    <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.settings.tabs.countries.astyrad_nskha', 'استيراد نسخة') }}</button>
                </form>
            @endcan
        </div>
    </div>

    {{-- القواعد المعروضة قبل أيّ فعل — فلا يُفاجَأ المالك بعد التنفيذ (2.11) --}}
    <ul class="mt-3 text-xs space-y-1" style="color: var(--text-muted)">
        <li>{{ setting('admin.settings.tabs.countries.la_hdhf_itlaqa_mn_hna_almhdhwf_mn_almsdr', '● لا حذف إطلاقًا من هنا — المحذوف من المصدر') }} <strong>{{ setting('admin.settings.tabs.countries.ykhfa', 'يُخفى') }}</strong> {{ setting('admin.settings.tabs.countries.bs_wsfh_byfdl_barqamh', 'بس، وصفّه بيفضل بأرقامه.') }}</li>
        <li>● <strong>{{ setting('admin.settings.tabs.countries.almhafza_la_tkhfa_abda', 'المحافظة لا تُخفى أبدًا') }}</strong> {{ setting('admin.settings.tabs.countries.hta_lw_ghabt_an_almsdr_tfdl_zahra_wmrbwta', '— حتّى لو غابت عن المصدر تفضل ظاهرة ومربوطة بأهلها.') }}</li>
        <li>{{ setting('admin.settings.tabs.countries.ay_dwla_aw_mhafza_mrtbta_bmstkhdm', '● أيّ دولة أو محافظة مرتبطة بمستخدم') }} <strong>{{ setting('admin.settings.tabs.countries.mhmya', 'محميّة') }}</strong> {{ setting('admin.settings.tabs.countries.aldmj_ma_bylmshash', '— الدمج ما بيلمسهاش.') }}</li>
    </ul>
</div>

{{--
    آخر فحص للمصدر ونتيجته (12.7-د): نجح/فشل **والسبب مكتوب** — فالفشل الصامت
    أسوأ من الفشل. واللون لا يحمل المعنى وحده: `<x-state-badge>` معه رمزُه
    ونصُّه دائمًا (2.16-ب).
--}}
<div class="card p-4 mt-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="text-sm font-semibold">{{ setting('countries.source.check.label', 'آخر فحص للمصدر') }}</div>

        @if ($lastCheck)
            <x-state-badge :state="$lastCheck->badgeState()" :label="$lastCheck->succeeded() ? setting('admin.settings.tabs.countries.njh', 'نجح') : setting('admin.settings.tabs.countries.fshl', 'فشل')" />
        @else
            <x-state-badge state="idle" :label="setting('admin.settings.tabs.countries.ma_atfhssh', 'ما اتفحصش')" />
        @endif
    </div>

    @if ($lastCheck)
        <p class="text-xs mt-2">{{ $lastCheck->message }}</p>
        <div class="text-xs mt-1" style="color: var(--text-muted)">
            {{ $lastCheck->created_at?->format('Y-m-d H:i') }}
            · {{ $lastCheck->trigger === 'schedule' ? setting('admin.settings.tabs.countries.fhs_dwry', 'فحص دوريّ') : setting('admin.settings.tabs.countries.fhs_ydwy', 'فحص يدويّ') }}
            @if ($lastCheck->succeeded())
                {!! strtr(setting('admin.settings.tabs.countries.mdaf_v1_mhdhwf_v2_madl', '· مضاف :v1 · محذوف :v2 · معدَّل'), [':v1' => e($lastCheck->added), ':v2' => e($lastCheck->removed)]) !!} {{ $lastCheck->changed }}
            @endif
        </div>
    @else
        <p class="text-xs mt-2">{{ setting('countries.source.check.never_text', 'لسّه ما اتفحصش المصدر ولا مرّة — اضغط «فحص المصدر الآن».') }}</p>
    @endif

    <div class="text-xs mt-2" style="color: var(--text-muted)">
        @if ($sync->checkEnabled())
            {{ setting('admin.settings.tabs.countries.alfhs_aldwry_shghal_almwad_alqadm', 'الفحص الدوريّ شغّال — الموعد القادم') }} {{ $sync->nextCheckAt()->format('Y-m-d H:i') }} ({{ $sync->checkTimezone() }}{{ setting('admin.settings.tabs.countries.walfhs', '). والفحص') }} <strong>{{ setting('admin.settings.tabs.countries.byqf_and_alfrwq', 'بيقف عند الفروق') }}</strong> {{ setting('admin.settings.tabs.countries.aldmj_qrark_int_mn_hna', '— الدمج قرارك إنت من هنا.') }}
        @else
            {{ setting('admin.settings.tabs.countries.alfhs_aldwry_mtwqf_mn_aliadadat_alfhs_alydwy', 'الفحص الدوريّ متوقّف من الإعدادات — الفحص اليدويّ لسّه شغّال.') }}
        @endif
    </div>
</div>

@if ($report)
    {{-- تقرير الدمج: مضاف/معدَّل/مخفيّ + **السجلّات المحميّة من الحذف** (24.3) --}}
    <div class="card p-4 mt-3">
        <div class="text-sm font-semibold flex items-center gap-2">
            <x-state-badge :state="$report['dry_run'] ? 'idle' : 'ok'" :label="$report['dry_run'] ? setting('admin.settings.tabs.countries.maayna', 'معاينة') : setting('admin.settings.tabs.countries.atnfdh', 'اتنفّذ')" />
            {{ setting('admin.settings.tabs.countries.tqryr_aldmj', 'تقرير الدمج') }}
        </div>
        <div class="text-xs mt-2" style="color: var(--text-muted)">
            {!! strtr(setting('admin.settings.tabs.countries.mdaf_v1_madl_v2_mkhfy_v3_mtrwk', 'مضاف :v1 · معدَّل :v2 · مخفيّ :v3 · متروك'), [':v1' => e($report['added']), ':v2' => e($report['updated']), ':v3' => e($report['hidden'])]) !!} {{ $report['skipped'] }}
        </div>
        @if ($report['protected'])
            <div class="mt-2 text-xs">
                <span class="font-semibold">{{ setting('admin.settings.tabs.countries.mhmya_mn_alhdhf', 'محميّة من الحذف (') }}{{ count($report['protected']) }}):</span>
                {{ implode(' · ', array_slice($report['protected'], 0, 20)) }}
            </div>
        @endif
    </div>
@endif

@if ($diff && ($diff['added'] || $diff['removed'] || $diff['changed']))
    {{-- جدول فروق النسخة الجديدة بثلاثة أعمدة: مضاف / محذوف / معدَّل (24.3) --}}
    <form method="post" action="{{ route('admin.countries.merge') }}" class="card p-4 mt-3">
        @csrf
        <input type="hidden" name="snapshot_id" value="{{ $snapshot->id }}">

        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-sm font-semibold">{{ setting('admin.settings.tabs.countries.frwq_alnskha', 'فروق النسخة') }} {{ $snapshot->version ? '('.$snapshot->version.')' : '' }}</div>
            <x-state-badge state="ok" :label="setting('admin.settings.tabs.countries.sydmj_bla_fqd', 'سيُدمج بلا فقد')" />
        </div>

        <div class="grid gap-3 md:grid-cols-3 mt-3">
            @foreach (['added', 'removed', 'changed'] as $bucket)
                <section aria-label="{{ $countries['changes'][$bucket] }}">
                    <div class="text-xs font-semibold mb-2">{{ $countries['changes'][$bucket] }} ({{ count($diff[$bucket]) }})</div>

                    @forelse ($diff[$bucket] as $row)
                        <label class="flex items-start gap-2 rounded-xl p-2 mb-2 text-xs"
                               style="background: var(--surface-sunken); border: 1px solid var(--border)">
                            <input type="checkbox" name="keys[]" value="{{ $row['key'] }}" class="mt-0.5"
                                   style="min-width: 1rem; min-height: 1rem">
                            <span class="min-w-0">
                                <span class="block font-semibold">{{ $row['label'] }}</span>
                                <span class="block" style="color: var(--text-muted)">{{ $row['note'] }}</span>
                                @if ($row['protected'])
                                    <span class="inline-block mt-1"><x-state-badge state="honor" :label="setting('admin.settings.tabs.countries.mhmya', 'محميّة')" /></span>
                                @endif
                                @if ($row['users'] > 0)
                                    <span class="block mt-1">{!! strtr(setting('admin.settings.tabs.countries.mrtbta_b_v1_mstkhdm', 'مرتبطة بـ:v1 مستخدم'), [':v1' => e($row['users'])]) !!}</span>
                                @endif
                            </span>
                        </label>
                    @empty
                        <p class="text-xs" style="color: var(--text-muted)">{{ setting('admin.settings.tabs.countries.mafysh', 'مافيش.') }}</p>
                    @endforelse
                </section>
            @endforeach
        </div>

        @can('countries_data.import')
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <button type="submit" name="dry_run" value="1" class="rounded-xl px-4 py-2 text-sm"
                        style="background: var(--surface-raised)">{{ setting('admin.settings.tabs.countries.dry_run_bla_ktaba', 'Dry-run (بلا كتابة)') }}</button>
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.settings.tabs.countries.admj_almkhtar', 'ادمج المختار') }}</button>
            </div>
        @endcan
    </form>
@elseif ($snapshot)
    <div class="card p-4 mt-3">
        <p class="text-sm">{{ setting('admin.settings.tabs.countries.alnskha_almrfwaa_mtabqa_lbyanatna_mafysh', 'النسخة المرفوعة مطابقة لبياناتنا — مافيش فروق. ✓') }}</p>
    </div>
@endif

{{-- ثلاثة فلاتر ظاهرة فقط (2.15-أ-4) --}}
<form method="get" action="{{ route('admin.settings.index') }}" class="card p-3 mt-3 flex flex-wrap items-end gap-3">
    <input type="hidden" name="tab" value="countries">

    <label class="block flex-1 min-w-[12rem]">
        <span class="block text-xs mb-1">{{ setting('admin.settings.tabs.countries.bhth', 'بحث') }}</span>
        <input type="search" name="cq" value="{{ $filters['q'] }}" placeholder="{{ setting('admin.settings.tabs.countries.dwla_aw_kwd_iso', 'دولة أو كود ISO…') }}"
               class="w-full rounded-xl px-3 py-2 text-sm"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
    </label>

    <label class="block">
        <span class="block text-xs mb-1">{{ setting('admin.settings.tabs.countries.alhala', 'الحالة') }}</span>
        <select name="cstatus" class="rounded-xl px-3 py-2 text-sm"
                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <option value="">{{ setting('admin.settings.tabs.countries.alkl', 'الكلّ') }}</option>
            <option value="active" @selected($filters['status'] === 'active')>{{ setting('admin.settings.tabs.countries.mfala', 'مفعّلة') }}</option>
            <option value="hidden" @selected($filters['status'] === 'hidden')>{{ setting('admin.settings.tabs.countries.mkhfya', 'مخفيّة') }}</option>
        </select>
    </label>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="cusers" value="1" @checked($filters['with_users'])> {{ setting('admin.settings.tabs.countries.fyha_mstkhdmwn', 'فيها مستخدمون') }}
    </label>

    <button class="rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.settings.tabs.countries.tsfya', 'تصفية') }}</button>
</form>

@if ($rows->isEmpty())
    <x-empty :message="setting('admin.settings.tabs.countries.mafysh_dwl_mtabqa_wsa_albhth', 'مافيش دول مطابقة — وسّع البحث.')" />
@else
    {{-- كروت رأسيّة على الموبايل بلا تمرير أفقيّ (2.15-ج) --}}
    <div class="space-y-2 mt-3">
        @foreach ($rows as $row)
            @php $country = $row['model']; @endphp
            <div class="card p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-semibold text-sm">{{ $country->name_ar }} <span style="color: var(--text-muted)">({{ $country->iso2 }})</span></div>
                        <div class="text-xs mt-0.5" style="color: var(--text-muted)">
                            {{ $country->phone_code ?: '—' }} · {{ $row['governorates'] }} {!! strtr(setting('admin.settings.tabs.countries.mhafza_v1_mstkhdm', 'محافظة · :v1 مستخدم ·'), [':v1' => e($row['users'])]) !!} {{ $country->timezone }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <x-state-badge :state="$country->is_active ? 'ok' : 'idle'"
                                       :label="$country->is_active ? setting('admin.settings.tabs.countries.zahra', 'ظاهرة') : setting('admin.settings.tabs.countries.mkhfya', 'مخفيّة')" />
                        @if ($country->sync_hidden_at)
                            <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.settings.tabs.countries.akhfaha_aldmj', 'أخفاها الدمج') }}</span>
                        @endif
                    </div>
                </div>

                @can('countries_data.edit')
                    <form method="post" action="{{ route('admin.countries.update', $country) }}"
                          class="flex flex-wrap items-end gap-2 mt-3">
                        @csrf
                        @method('put')
                        <label class="flex-1 min-w-[10rem]">
                            <span class="block text-xs mb-1">{{ setting('admin.settings.tabs.countries.alasm_alarby', 'الاسم العربيّ') }}</span>
                            <input type="text" name="name_ar" value="{{ $country->name_ar }}" required
                                   class="w-full rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" value="1" @checked($country->is_active)> {{ setting('admin.settings.tabs.countries.zahra', 'ظاهرة') }}
                        </label>
                        <button class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.settings.tabs.countries.hfz', 'حفظ') }}</button>
                    </form>
                @endcan
            </div>
        @endforeach
    </div>
@endif

<p class="text-xs mt-3" style="color: var(--text-muted)">
    {{ $attribution }}
    @if ($sourceUrl !== '')
        <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer"
           style="color: var(--color-brand-500)">{{ setting('admin.settings.tabs.countries.sfha_almsdr', 'صفحة المصدر') }}</a>
    @endif
</p>
