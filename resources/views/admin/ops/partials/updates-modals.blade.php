@can('updates.manage')
    @push('modals')
        {{-- ⛔ تأكيد مزدوج: عبارة يكتبها الأدمن بيده + إقرار صريح — ولا تنفيذ قبلهما --}}
        <x-modal id="migrate-confirm" :title="setting('admin.ops.partials.updates_modals.takyd_tnfydh_altrhyl', 'تأكيد تنفيذ الترحيل')">
            <form method="post" action="{{ route('admin.ops.updates.migrate') }}" class="space-y-3">
                @csrf

                <p class="text-sm">
                    {{ setting('admin.ops.partials.updates_modals.hytnfdh', 'هيتنفّذ') }} <strong>{{ count($pending) }}</strong> {{ setting('admin.ops.partials.updates_modals.hjra_ala_qaada_albyanat_alhya', 'هجرة على قاعدة البيانات الحيّة.') }}
                    @if (setting('updates.backup_before_migrate', true))
                        {{ setting('admin.ops.partials.updates_modals.hnakhd_nskha_ahtyatya_qblha_wntakd_inha', 'هناخد نسخة احتياطيّة قبلها ونتأكّد إنّها سليمة.') }}
                    @endif
                    @if (setting('updates.maintenance_enabled', true))
                        {{ setting('admin.ops.partials.updates_modals.walmnsa_htdkhl', 'والمنصّة هتدخل') }} <strong>{{ setting('admin.ops.partials.updates_modals.wda_alsyana', 'وضع الصيانة') }}</strong> {{ setting('admin.ops.partials.updates_modals.athna_altrhyl_wtkhrj_mnh_tlqayya_badh', 'أثناء الترحيل وتخرج منه تلقائيًّا بعده.') }}
                    @endif
                </p>

                @unless ($preflightOk)
                    <div class="rounded-xl p-3 text-sm"
                         style="background: color-mix(in srgb, var(--color-state-danger) 12%, transparent); color: var(--color-state-danger)">
                        {{ setting('admin.ops.partials.updates_modals.fy_fhs_qbly_ma_adash_altnfydh_hytwqf_qbl_ma', '✗ في فحص قبليّ ما عدّاش — التنفيذ هيتوقف قبل ما يلمس حاجة. صلّح الفحص الأوّل.') }}
                    </div>
                @endunless

                @if ($pending !== [])
                    <ul class="rounded-xl p-3 max-h-40 overflow-y-auto" style="background: var(--surface-sunken)">
                        @foreach ($pending as $name)
                            <li class="font-mono text-xs break-all py-1">{{ $name }}</li>
                        @endforeach
                    </ul>
                @endif

                <x-form.input name="confirm" :label="strtr(setting('admin.ops.partials.updates_modals.aktb_v1_lltakyd', 'اكتب «:v1» للتأكيد'), [':v1' => e($confirmPhrase)])"
                              :hint="setting('admin.ops.partials.updates_modals.alktaba_alydwya_mqswda_ashan_mafysh_dghta', 'الكتابة اليدويّة مقصودة — عشان مافيش ضغطة بالغلط.')" autocomplete="off" />

                <label class="flex items-start gap-2 text-sm" style="min-height: 44px">
                    <input type="checkbox" name="understood" value="1" class="mt-1">
                    <span>{{ setting('admin.ops.partials.updates_modals.fahm_in_alamlya_dy_btghyr_qaada_albyanat', 'فاهم إنّ العمليّة دي بتغيّر قاعدة البيانات، ومسجَّلة باسمي في سجلّ التدقيق.') }}</span>
                </label>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.ops.partials.updates_modals.nfdh_dlwqty', 'نفّذ دلوقتي') }}</button>
            </form>
        </x-modal>
    @endpush
@endcan

@php($failureReport = session('ops.failure') ?? (($lastFailure?->report) ? json_decode($lastFailure->report, true) : null))

@can('backups.restore')
    @if (is_array($failureReport) && ! empty($failureReport['backup_file_id']))
        @push('modals')
            {{-- الاستعادة تكتب فوق البيانات الحاليّة — فتأكيدها مكتوب هي كمان (2.11-ح) --}}
            <x-modal id="restore-confirm" :title="setting('admin.ops.partials.updates_modals.astrjaa_mn_alnskha_alahtyatya', 'استرجاع من النسخة الاحتياطيّة')">
                <form method="post" action="{{ route('admin.ops.updates.restore', $failureReport['backup_file_id']) }}" class="space-y-3">
                    @csrf

                    <div class="rounded-xl p-3 text-sm"
                         style="background: color-mix(in srgb, var(--color-state-danger) 12%, transparent); color: var(--color-state-danger)">
                        {{ setting('admin.ops.partials.updates_modals.hnrja_albyanat_lhaltha_lhza_alnskha', '◉ هنرجّع البيانات لحالتها لحظة النسخة') }} <strong>{{ $failureReport['backup_file'] ?? '' }}</strong> {{ setting('admin.ops.partials.updates_modals.way_haja_atktbt_bad_kdh_httshal_sjl_altdqyq', '— وأيّ حاجة اتكتبت بعد كده هتتشال. سجلّ التدقيق وسجلّ النسخ مابيتمسّوش.') }}
                    </div>

                    <label class="block">
                        <span class="block text-sm mb-1">{!! strtr(setting('admin.ops.partials.updates_modals.aktb_v1_lltakyd', 'اكتب «:v1» للتأكيد'), [':v1' => e($restorePhrase)]) !!}</span>
                        <input type="text" name="confirm" autocomplete="off"
                               class="w-full rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>

                    <label class="flex items-start gap-2 text-sm" style="min-height: 44px">
                        <input type="checkbox" name="understood" value="1" class="mt-1">
                        <span>{{ setting('admin.ops.partials.updates_modals.fahm_in_alastaada_btktb_fwq_albyanat_alhalya', 'فاهم إنّ الاستعادة بتكتب فوق البيانات الحاليّة، ومسجَّلة باسمي.') }}</span>
                    </label>

                    <button class="w-full rounded-xl px-4 py-3 text-sm font-semibold"
                            style="background: var(--surface-raised); color: var(--color-state-danger)">{{ setting('admin.ops.partials.updates_modals.astad_dlwqty', 'استعِد دلوقتي') }}</button>
                </form>
            </x-modal>
        @endpush
    @endif
@endcan

@can('updates.restore')
    @push('modals')
        <x-modal id="rollback-confirm" :title="setting('admin.ops.partials.updates_modals.takyd_alastrjaa', 'تأكيد الاسترجاع')">
            <form method="post" action="{{ route('admin.ops.updates.rollback') }}" class="space-y-3">
                @csrf

                <div class="rounded-xl p-3 text-sm"
                     style="background: color-mix(in srgb, var(--color-state-danger) 12%, transparent); color: var(--color-state-danger)">
                    {{ setting('admin.ops.partials.updates_modals.alastrjaa_byshyl_akhr_dfaa_hjrat', '◉ الاسترجاع بيشيل آخر دفعة هجرات —') }} <strong>{{ setting('admin.ops.partials.updates_modals.walbyanat_ally_fy_jdawlha_httmsh_wla_trja', 'والبيانات اللي في جداولها هتتمسح ولا ترجع') }}</strong>.
                </div>

                @if ($lastBatch !== [])
                    <ul class="rounded-xl p-3 max-h-40 overflow-y-auto" style="background: var(--surface-sunken)">
                        @foreach ($lastBatch as $name)
                            <li class="font-mono text-xs break-all py-1">{{ $name }}</li>
                        @endforeach
                    </ul>
                @endif

                <label class="block">
                    <span class="block text-sm mb-1">{!! strtr(setting('admin.ops.partials.updates_modals.aktb_v1_lltakyd', 'اكتب «:v1» للتأكيد'), [':v1' => e($rollbackPhrase)]) !!}</span>
                    <input type="text" name="confirm" id="rollback-confirm-phrase" autocomplete="off"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <label class="flex items-start gap-2 text-sm" style="min-height: 44px">
                    <input type="checkbox" name="understood" value="1" class="mt-1">
                    <span>{{ setting('admin.ops.partials.updates_modals.fahm_in_ally_hytshal_msh_hyrja_winy_mswwl_an', 'فاهم إنّ اللي هيتشال مش هيرجع، وإنّي مسؤول عن القرار ده.') }}</span>
                </label>

                <button class="w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--surface-raised); color: var(--color-state-danger)">{{ setting('admin.ops.partials.updates_modals.astrja_akhr_dfaa', 'استرجع آخر دفعة') }}</button>
            </form>
        </x-modal>
    @endpush
@endcan
