@extends('layouts.admin')

@section('title', setting('admin.volunteer.certificates.shhadat_alttwa', 'شهادات التطوّع'))

@section('content')
    <x-page-header
        :title="setting('admin.volunteer.certificates.shhadat_alttwa', 'شهادات التطوّع')"
        :subtitle="setting('admin.volunteer.certificates.arbaa_anwaa_la_khams_lha_mjanya_balkaml_wbla', 'أربعة أنواع لا خامس لها — مجّانيّة بالكامل، وبلا أيّ أرقام داخليّة على الورقة.')"
        :breadcrumbs="[['label' => setting('admin.volunteer.certificates.alttwa', 'التطوّع'), 'url' => route('admin.volunteer.index')], ['label' => setting('admin.volunteer.certificates.alshhadat', 'الشهادات')]]">
        <x-slot:action>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                @can('volunteer_certificates.create')
                    <button type="button" data-modal-open="appreciation-form"
                            class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.certificates.isdar_ydwy', 'إصدار يدويّ (تقدير استثنائيّة)') }}</button>
                    <form method="post" action="{{ route('admin.volunteer.certificates.auto-issue') }}">
                        @csrf
                        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.volunteer.certificates.shghl_alisdar_altlqayy', 'شغّل الإصدار التلقائيّ') }}</button>
                    </form>
                @endcan
                <a href="{{ route('verify.certificate') }}" target="_blank" rel="noopener"
                   class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.volunteer.certificates.sfha_althqq', 'صفحة التحقّق') }}</a>
            </div>
        </x-slot:action>
    </x-page-header>

    @include('admin.volunteer.partials.tabs', ['current' => 'certificates'])

    {{-- شرطا الاستحقاق معلنان في أعلى الشاشة — مانع التضخّم (13.4-ع-ب) --}}
    <div class="card p-3 mb-4 text-sm space-y-1">
        <div>{{ setting('admin.volunteer.certificates.almda_fy_albwzshn', '① المدّة في البوزشن ≥') }} <strong>{{ $minDays }}</strong> {{ setting('admin.volunteer.certificates.ywma', 'يومًا (') }}<code>volunteer_cert.min_days_in_position</code>).</div>
        <div>② <strong>{{ setting('admin.volunteer.certificates.drja_alaltzam_ghyr_salba', 'درجة الالتزام غير سالبة') }}</strong> {{ setting('admin.volunteer.certificates.wqt_alisdar', 'وقت الإصدار.') }}</div>
        <div><x-icon name="lock" size="16" /> {{ setting('admin.volunteer.certificates.shhada_wahda_lkl_bwzshn_kyan_waltrqya_tsdr', 'شهادة واحدة لكلّ (بوزشن × كيان) — والترقية تُصدر الأعلى لا نسخة مكرّرة.') }}</div>
    </div>

    {{-- تابا الشاشة (24.2): القوالب · السجلّ الصادر --}}
    <div class="flex items-center gap-2 mb-4">
        <a href="{{ route('admin.volunteer.certificates', ['tab' => 'templates']) }}"
           class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
           style="{{ $tab === 'templates' ? 'background: var(--color-brand-500); color:#04201c' : 'background: var(--surface-sunken); color: var(--text)' }}">{{ setting('admin.volunteer.certificates.alqwalb', 'القوالب') }}</a>
        <a href="{{ route('admin.volunteer.certificates', ['tab' => 'ledger']) }}"
           class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
           style="{{ $tab === 'ledger' ? 'background: var(--color-brand-500); color:#04201c' : 'background: var(--surface-sunken); color: var(--text)' }}">{{ setting('admin.volunteer.certificates.alsjl_alsadr', 'السجلّ الصادر') }}</a>
    </div>

    @unless (auth()->user()->allows('volunteer_certificates.edit') || auth()->user()->allows('volunteer_certificates.create'))
        <p class="text-xs mb-3" style="color: var(--text-muted)">{{ setting('admin.volunteer.certificates.qraa_fqt', 'وضع القراءة فقط — بلا إصدار ولا إلغاء ولا تحرير قوالب.') }}</p>
    @endunless

    @if ($tab === 'templates')
        @include('admin.volunteer.certificates.partials.templates-tab', ['cards' => $cards, 'types' => $types])
    @else
        @include('admin.volunteer.certificates.partials.ledger-tab', [
            'view' => $view,
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'pending' => $pending ?? collect(),
            'issued' => $issued ?? collect(),
            'total' => $total ?? 0,
            'nextOffset' => $nextOffset ?? 0,
            'hasMore' => $hasMore ?? false,
            'pageSize' => $pageSize ?? 0,
        ])
    @endif

    @can('volunteer_certificates.edit')
        @include('admin.volunteer.partials.settings-card', [
            'title' => setting('admin.volunteer.certificates.shrwt_alasthqaq_walanwaa_walqwalb', 'شروط الاستحقاق والأنواع والقوالب'),
            'rows' => $settings,
            'action' => route('admin.volunteer.certificates.settings.save'),
            'resetAction' => route('admin.volunteer.reset', 'volunteer_cert'),
            'lockedKeys' => [
                'volunteer_cert.one_per_position_entity',
                'volunteer_cert.free_locked',
                'volunteer_cert.hide_internal_numbers',
                'volunteer_cert.revoke_only_on_fraud',
            ],
            'open' => false,
        ])
    @endcan
@endsection

@push('modals')
    @can('volunteer_certificates.create')
        {{-- ⭐ إصدار يدويّ — تقدير استثنائيّة وحدها: مستفيدٌ بالكود ومبرّرٌ إلزاميّ (13.4-ع-4 · 24.2) --}}
        <x-modal id="appreciation-form" :title="setting('admin.volunteer.certificates.isdar_shhadat_tqdyr', 'إصدار شهادة تقدير استثنائيّة')">
            <form method="post" action="{{ route('admin.volunteer.certificates.issue-appreciation') }}" class="space-y-3">
                @csrf
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.volunteer.certificates.tqdyr_hint', 'النوع الوحيد الذي يُمنَح يدويًّا — مشرف الشهر · نادي +9.5 · إنجاز خاصّ. ومتكرّرٌ بطبيعته لا يُحجَب بشهادةٍ سابقة.') }}</p>

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.volunteer.certificates.almstfyd_balkwd', 'المستفيد (بالكود)') }}</span>
                    <input type="text" name="code" required maxlength="32"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.volunteer.certificates.almbrr_alilzamy', 'المبرّر (إلزاميّ)') }}</span>
                    <textarea name="reason" rows="3" required minlength="10" maxlength="500"
                              class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.certificates.asdr_alshhada', 'أصدر الشهادة') }}</button>
            </form>
        </x-modal>
    @endcan

    @can('volunteer_certificates.edit')
        <x-modal id="revoke-modal" :title="setting('admin.volunteer.certificates.ilgha_shhada_lltzwyr_almthbt_whdh', 'إلغاء شهادة — للتزوير المثبَت وحده')">
            <form method="post" action="{{ route('admin.volunteer.certificates.revoke', 0) }}" id="revoke-form">
                @csrf
                <p class="text-sm mb-3" style="color: var(--color-state-warn)">
                    {{ setting('admin.volunteer.certificates.alilgha_astthna_wahd', '▲ الإلغاء استثناء واحد:') }} <strong>{{ setting('admin.volunteer.certificates.altzwyr_aw_alghsh_almthbt', 'التزوير أو الغشّ المثبَت') }}</strong>{{ setting('admin.volunteer.certificates.waliqsa_whdh', '. والإقصاء وحده') }} <strong>{{ setting('admin.volunteer.certificates.la_ylghy', 'لا يُلغي') }}</strong> {{ setting('admin.volunteer.certificates.shhada_an_aml_hqyqy', 'شهادةً عن عمل حقيقيّ.') }}
                </p>

                <label class="block text-sm font-semibold mb-1" for="rev-reason">{{ setting('admin.volunteer.certificates.alqrar_almwthq', 'القرار الموثّق') }}</label>
                <textarea name="reason" id="rev-reason" rows="3" required minlength="10" maxlength="500"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>

                <label class="flex items-center gap-2 text-sm mb-3">
                    <input type="checkbox" name="fraud_confirmed" value="1" required>
                    {{ setting('admin.volunteer.certificates.aqr_ban_altzwyr_mthbt_wmwthq', 'أُقرّ بأنّ التزوير مثبَت وموثّق.') }}
                </label>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-state-danger); color: #fff">{{ setting('admin.volunteer.certificates.algh_alshhada', 'ألغِ الشهادة') }}</button>
            </form>
        </x-modal>
    @endcan
@endpush

@push('scripts')
    <script>
        {{-- ⭐ تفويضٌ لا ربطٌ مباشر: صفوف السجلّ تصل لاحقًا بالتمرير التدريجيّ (13.1) --}}
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-revoke]');
            if (!btn) return;

            const form = document.getElementById('revoke-form');
            form.action = '{{ route('admin.volunteer.certificates.revoke', 0) }}'.replace(/0$/, btn.dataset.id);
            const modal = document.getElementById('revoke-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        });
    </script>
@endpush
