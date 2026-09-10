@extends('layouts.admin')

@section('title', setting('admin.referral_admin.index.alryfyral_walsfra', 'الريفيرال والسفراء'))

@php
    /**
     * لوحة إدارة الريفيرال والسفراء (24.2).
     *
     * ثلاثة تابات بتحميل كسول · 4 كروت KPI · 3 فلاتر ظاهرة · نطاق 30 يومًا افتراضيًّا.
     * 🔒 والعمولة رقمٌ ماليّ: عمودها كلّه **يختفي** لغير مالك المنصّة (12.7).
     */
    $u = auth()->user();
    $canManage = $u?->can('referrals.manage');
    $canTiers = $u?->can('ambassadors.manage') || $u?->can('ambassadors.edit');
    $canExport = $u?->can('referrals.export') || $u?->can('ambassadors.export');

    $tabLinks = collect($tabs)->map(fn ($label, $key) => [
        'key' => $key,
        'label' => $label,
        'url' => route('admin.referrals.index', array_merge(request()->query(), ['tab' => $key])),
    ])->values()->all();
@endphp

@section('content')
    <x-page-header :title="setting('admin.referral_admin.index.alryfyral_walsfra', 'الريفيرال والسفراء')"
                   :subtitle="setting('admin.referral_admin.index.myn_daa_myn_wiyh_almkafat_almalqa_wiyh_slm', 'مين دعا مين، وإيه المكافآت المعلّقة، وإيه سلّم الألقاب — في شاشة واحدة.')"
                   :breadcrumbs="[['label' => setting('admin.referral_admin.index.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')], ['label' => setting('admin.referral_admin.index.alryfyral_walsfra', 'الريفيرال والسفراء')]]">
        <x-slot:action>
            @if ($canManage)
                <a href="{{ route('admin.referrals.index', ['tab' => 'invites', 'payout' => 'pending']) }}"
                   class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.referral_admin.index.raja_almkafat_almalqa', 'راجع المكافآت المعلّقة') }}</a>
            @endif

            <details class="relative">
                <summary class="btn cursor-pointer rounded-xl px-3 py-2 text-sm select-none"
                         style="background: var(--surface-sunken); border: 1px solid var(--border)">⋯</summary>
                <div class="card absolute end-0 mt-2 p-2 w-56 z-20 text-sm space-y-1">
                    <a href="{{ route('ambassadors.index') }}" class="block rounded-lg px-3 py-2 hover:underline">{{ setting('admin.referral_admin.index.maayna_sfha_aldawat', 'معاينة صفحة الدعوات') }}</a>
                    @if ($canExport)
                        <a href="{{ route('admin.referrals.export', request()->query()) }}" class="block rounded-lg px-3 py-2 hover:underline">{{ setting('admin.referral_admin.index.tsdyr_almdawyn', 'تصدير المدعوّين') }}</a>
                    @endif
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-kpi :label="setting('admin.referral_admin.index.dawat_almda', 'دعوات المدى')" :value="$stats['invites']" icon="envelope" />
        <x-kpi :label="setting('admin.referral_admin.index.mktmla', 'مكتملة')" :value="$stats['completed']" icon="check" />
        <x-kpi :label="setting('admin.referral_admin.index.mkafat_malqa', 'مكافآت معلّقة')" :value="$stats['pending']" icon="hourglass" />
        @if ($canSeeMoney)
            <x-kpi :label="setting('admin.referral_admin.index.alamwla_almsthqa', 'العمولة المستحقّة')" :value="number_format($stats['commission'], 2)" icon="money" />
        @else
            <x-kpi :label="setting('admin.referral_admin.index.sfra_mtlqbwn', 'سفراء متلقّبون')" :value="$stats['ambassadors']" icon="crown" />
        @endif
    </div>

    <x-tabs :tabs="$tabLinks" :current="$tab" />

    @if ($tab === 'invites')
        <x-filters :action="route('admin.referrals.index')">
            <input type="hidden" name="tab" value="invites">

            <label class="text-sm grow min-w-40">{{ setting('admin.referral_admin.index.bhth_baldaay_aw_almdaw', 'بحث بالداعي أو المدعو') }}
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('admin.referral_admin.index.asm_aw_kwd', 'اسم أو كود') }}"
                       class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <label class="text-sm">{{ setting('admin.referral_admin.index.hala_aldawa', 'حالة الدعوة') }}
                <select name="status" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('admin.referral_admin.index.alkl', 'الكلّ') }}</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">{{ setting('admin.referral_admin.index.almkafaa', 'المكافأة') }}
                <select name="payout" class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('admin.referral_admin.index.alkl', 'الكلّ') }}</option>
                    @foreach ($payouts as $key => $label)
                        <option value="{{ $key }}" @selected($filters['payout'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.referral_admin.index.fltra', 'فلترة') }}</button>

            <x-slot:advanced>
                <label class="text-sm">{{ setting('admin.referral_admin.index.mn_tarykh', 'من تاريخ') }}
                    <input type="date" name="from" value="{{ $filters['from'] }}"
                           class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="text-sm">{{ setting('admin.referral_admin.index.ila_tarykh', 'إلى تاريخ') }}
                    <input type="date" name="to" value="{{ $filters['to'] }}"
                           class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="flagged" value="1" @checked($filters['flagged'] === '1')>
                    <span>{{ setting('admin.referral_admin.index.almshbwh_almtkrr_fqt', 'المشبوه/المتكرّر فقط') }}</span>
                </label>
            </x-slot:advanced>
        </x-filters>

        @if ($invites->isEmpty())
            {{-- تمييز «لسّه مافيش دعوات أصلًا» عن «الفلتر ما طابقش حاجة» (24.2) --}}
            <x-empty :message="setting('referral_admin.empty_text', 'لسّه مافيش دعوات في المدى ده — جرّب مدى أوسع.')"
                     :filtered="$filters['q'] !== '' || $filters['status'] !== '' || $filters['payout'] !== '' || $filters['flagged'] !== '' || $filters['from'] !== '' || $filters['to'] !== ''" />
        @else
            <div class="card p-0 overflow-hidden hidden md:block">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background: var(--surface-sunken)">
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.referral_admin.index.aldaay', 'الداعي') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.referral_admin.index.almdaw', 'المدعو') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.referral_admin.index.tarykh_aldawa', 'تاريخ الدعوة') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.referral_admin.index.alhala', 'الحالة') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.referral_admin.index.tdhkra_altrhyb', 'تذكرة الترحيب') }}</th>
                            @if ($canSeeMoney)
                                <th class="text-start px-4 py-3 font-semibold"><x-icon name="lock" size="16" /> {{ setting('admin.referral_admin.index.alamwla', 'العمولة') }}</th>
                            @endif
                            <th class="text-start px-4 py-3 font-semibold">⋯</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invites as $referral)
                            @php $status = $service->statusOf($referral); @endphp
                            <tr style="border-top: 1px solid var(--border)">
                                <td class="px-4 py-3">{{ $referral->referrer?->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $referral->referred?->name ?? setting('admin.referral_admin.index.lm_ysjl_bad', 'لم يسجّل بعد') }}</td>
                                <td class="px-4 py-3">{{ $referral->created_at?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <x-state-badge :state="match ($status) { 'completed' => 'ok', 'waiting' => 'warn', default => 'idle' }"
                                                   :label="$statuses[$status]" />
                                </td>
                                <td class="px-4 py-3">
                                    <x-state-badge :state="$referral->welcome_ticket_granted ? 'ok' : 'idle'"
                                                   :label="$referral->welcome_ticket_granted ? setting('admin.referral_admin.index.srft', 'صُرفت') : ($payouts[$referral->payout_status] ?? setting('admin.referral_admin.index.malqa', 'معلّقة'))" />
                                </td>
                                @if ($canSeeMoney)
                                    <td class="px-4 py-3">{{ number_format((float) $referral->commission_earned, 2) }}</td>
                                @endif
                                <td class="px-4 py-3">
                                    @include('admin.referral-admin.partials.row-actions', ['referral' => $referral])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 md:hidden">
                @foreach ($invites as $referral)
                    @php $status = $service->statusOf($referral); @endphp
                    <article class="card p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold">{{ $referral->referrer?->name ?? '—' }}</p>
                                <p class="text-xs" style="color: var(--text-muted)">{{ setting('admin.referral_admin.index.daa', 'دعا:') }} {{ $referral->referred?->name ?? setting('admin.referral_admin.index.lm_ysjl_bad', 'لم يسجّل بعد') }}</p>
                            </div>
                            <x-state-badge :state="match ($status) { 'completed' => 'ok', 'waiting' => 'warn', default => 'idle' }"
                                           :label="$statuses[$status]" />
                        </div>
                        <p class="text-xs mt-2" style="color: var(--text-muted)">{{ $referral->created_at?->format('Y-m-d') }}</p>
                        <div class="mt-3 pt-3" style="border-top: 1px solid var(--border)">
                            @include('admin.referral-admin.partials.row-actions', ['referral' => $referral])
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-4">{{ $invites->links() }}</div>
        @endif
    @elseif ($tab === 'ambassadors')
        <x-filters :action="route('admin.referrals.index')">
            <input type="hidden" name="tab" value="ambassadors">
            <label class="text-sm grow min-w-40">{{ setting('admin.referral_admin.index.bhth_balasm_aw_alkwd', 'بحث بالاسم أو الكود') }}
                <input type="search" name="q" value="{{ $filters['q'] }}"
                       class="block w-full rounded-xl px-3 py-2 text-sm mt-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.referral_admin.index.fltra', 'فلترة') }}</button>
        </x-filters>

        @if ($ambassadors->isEmpty())
            {{-- تمييز «لسّه محدّش وصل أصلًا» عن «الفلتر ما طابقش حاجة» (24.2) --}}
            <x-empty :message="setting('admin.referral_admin.index.lsh_mhdsh_wsl_lawl_lqb_awl_dawa_mfala_hy', 'لسّه محدّش وصل لأوّل لقب — أوّل دعوة مفعّلة هي البداية.')"
                     :filtered="$filters['q'] !== ''" />
        @else
            <div class="card p-0 overflow-hidden hidden md:block">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background: var(--surface-sunken)">
                            <th class="text-start px-4 py-3 font-semibold">#</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.referral_admin.index.alsfyr', 'السفير') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.referral_admin.index.dawat_mfala', 'دعوات مفعَّلة') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.referral_admin.index.allqb', 'اللقب') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.referral_admin.index.tarykh_allqb', 'تاريخ اللقب') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">⋯</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ambassadors as $index => $ambassador)
                            <tr style="border-top: 1px solid var(--border)">
                                <td class="px-4 py-3">{{ $index + 1 }}</td>
                                <td class="px-4 py-3">{{ $ambassador->name }} <span class="text-xs" style="color: var(--text-muted)">{{ $ambassador->code }}</span></td>
                                <td class="px-4 py-3">{{ $ambassador->ambassador_invites }}</td>
                                <td class="px-4 py-3">
                                    @if ($ambassador->ambassador_title)
                                        <x-state-badge state="honor" :label="$ambassador->ambassador_title" />
                                    @else
                                        <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.referral_admin.index.bla_lqb_bad', 'بلا لقب بعد') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs">{{ $ambassador->ambassador_granted_at?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs">
                                    <a class="underline" href="{{ route('admin.referrals.audit', $ambassador) }}">{{ setting('admin.referral_admin.index.tdqyq_shbkth', 'تدقيق شبكته') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
            <div class="grid gap-3 md:hidden">
                @foreach ($ambassadors as $index => $ambassador)
                    <article class="card p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold">{{ $index + 1 }}. {{ $ambassador->name }}</p>
                                <p class="text-xs" style="color: var(--text-muted)">{{ $ambassador->code }}</p>
                            </div>
                            @if ($ambassador->ambassador_title)
                                <x-state-badge state="honor" :label="$ambassador->ambassador_title" />
                            @endif
                        </div>
                        <p class="text-xs mt-2" style="color: var(--text-muted)">
                            {{ $ambassador->ambassador_invites }} {{ setting('admin.referral_admin.index.dawa_mfala', 'دعوة مفعَّلة') }}
                            @if ($ambassador->ambassador_granted_at) · {{ $ambassador->ambassador_granted_at->format('Y-m-d') }} @endif
                        </p>
                        <div class="mt-3 pt-3 text-xs" style="border-top: 1px solid var(--border)">
                            <a class="underline" href="{{ route('admin.referrals.audit', $ambassador) }}">{{ setting('admin.referral_admin.index.tdqyq_shbkth', 'تدقيق شبكته') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @else
        {{-- تاب العتبات والألقاب — صفوف يعدّلها الأدمن ويحفظها دفعةً واحدة --}}
        @if (! $canTiers)
            <x-empty :message="setting('admin.referral_admin.index.malksh_slahya_tadyl_slm_alalqab_klm_malk', 'مالكش صلاحيّة تعديل سلّم الألقاب — كلّم مالك المنصّة.')" />
        @else
            <form method="post" action="{{ route('admin.referrals.tiers') }}" class="card p-4">
                @csrf
                <p class="text-sm mb-3" style="color: var(--text-muted)">
                    {{ setting('admin.referral_admin.index.alqab_fqt_bla_sharat_7_6_1_waltrtyb_bytzbt', 'ألقاب فقط بلا شارات (7.6.1) — والترتيب بيتظبط تلقائيًّا حسب عدد الدعوات لمّا تحفظ.') }}
                </p>

                <div class="space-y-3" data-tier-rows>
                    @foreach ($tiers as $index => $tier)
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2" data-tier-row>
                            <input type="text" name="tiers[{{ $index }}][key]" value="{{ $tier['key'] }}" required maxlength="32"
                                   placeholder="{{ setting('admin.referral_admin.index.almftah_bronze', 'المفتاح (bronze)') }}" class="rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <input type="text" name="tiers[{{ $index }}][label]" value="{{ $tier['label'] }}" required maxlength="64"
                                   placeholder="{{ setting('admin.referral_admin.index.allqb_alzahr', 'اللقب الظاهر') }}" class="rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <input type="number" name="tiers[{{ $index }}][threshold]" value="{{ $tier['threshold'] }}" required min="1" max="100000"
                                   placeholder="{{ setting('admin.referral_admin.index.add_aldawat', 'عدد الدعوات') }}" class="rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-2 mt-4">
                    <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.referral_admin.index.ahfz_alslm', 'احفظ السلّم') }}</button>
                    <button type="button" data-tier-add class="text-xs underline" style="color: var(--text-muted)">{{ setting('admin.referral_admin.index.lqb_jdyd', '+ لقب جديد') }}</button>
                </div>
            </form>
        @endif
    @endif

    @if ($canManage)
        @include('admin.screens24.settings', [
            'settings' => $settings,
            'saveRoute' => route('admin.referrals.settings'),
            'resetRoute' => route('admin.referrals.settings.reset'),
            'blockTitle' => setting('admin.referral_admin.index.iadadat_alryfyral_walsfra', 'إعدادات الريفيرال والسفراء'),
        ])
    @endif
@endsection

@push('modals')
    @if ($canManage)
        <x-modal id="payout-modal" :title="setting('admin.referral_admin.index.srf_mkafaa_malqa', 'صرف مكافأة معلّقة')">
            <form method="post" action="{{ route('admin.referrals.index') }}" data-payout-form>
                @csrf
                <p class="text-sm mb-3" style="color: var(--text-muted)">
                    {{ setting('admin.referral_admin.index.alsrf_bytm_lma_ykwn_almdaw_fal_hsabh_walshrt', 'الصرف بيتمّ لما يكون المدعوّ فعّل حسابه — والشرط ده بيتظبط من إعدادات الشاشة.') }}
                </p>
                <label class="block text-sm font-semibold mb-1" for="payout-note">{{ setting('admin.referral_admin.index.mlahza_akhtyarya', 'ملاحظة (اختياريّة)') }}</label>
                <textarea name="note" id="payout-note" rows="2" maxlength="500"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-4"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical"></textarea>
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.referral_admin.index.asrf_almkafaa', 'اصرف المكافأة') }}</button>
            </form>
        </x-modal>

        <x-modal id="hold-modal" :title="setting('admin.referral_admin.index.talyq_almkafaa', 'تعليق المكافأة')">
            <form method="post" action="{{ route('admin.referrals.index') }}" data-hold-form>
                @csrf
                <label class="block text-sm font-semibold mb-1" for="hold-note">{{ setting('admin.referral_admin.index.sbb_altalyq', 'سبب التعليق') }}</label>
                <textarea name="note" id="hold-note" rows="2" maxlength="500" required
                          placeholder="{{ setting('admin.referral_admin.index.mthal_nmt_dawat_mtkrr_mn_nfs_aljhaz_mhtaj', 'مثال: نمط دعوات متكرّر من نفس الجهاز — محتاج تدقيق.') }}"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-4"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical"></textarea>
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.referral_admin.index.alq_almkafaa', 'علّق المكافأة') }}</button>
            </form>
        </x-modal>
    @endif
@endpush

@push('scripts')
    <script>
        (() => {
            const open = (id) => {
                const modal = document.getElementById(id);
                if (!modal) return;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };

            const bind = (selector, formSelector, modalId) => {
                const form = document.querySelector(formSelector);
                document.querySelectorAll(selector).forEach((btn) => {
                    btn.addEventListener('click', () => {
                        if (!form) return;
                        form.action = btn.dataset.action;
                        open(modalId);
                    });
                });
            };

            bind('[data-referral-payout]', '[data-payout-form]', 'payout-modal');
            bind('[data-referral-hold]', '[data-hold-form]', 'hold-modal');

            // صفّ لقب جديد — بلا مكتبة، نسخة من آخر صفّ بفهرس جديد
            const rows = document.querySelector('[data-tier-rows]');
            const add = document.querySelector('[data-tier-add]');

            if (rows && add) {
                add.addEventListener('click', () => {
                    const index = rows.querySelectorAll('[data-tier-row]').length;
                    const row = document.createElement('div');
                    row.className = 'grid grid-cols-1 md:grid-cols-3 gap-2';
                    row.setAttribute('data-tier-row', '');
                    row.innerHTML = ['key', 'label', 'threshold'].map((field) => {
                        const type = field === 'threshold' ? 'number' : 'text';
                        return `<input type="${type}" name="tiers[${index}][${field}]" required class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">`;
                    }).join('');
                    rows.appendChild(row);
                });
            }
        })();
    </script>
@endpush
