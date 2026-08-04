@extends('layouts.admin')

@section('title', setting('admin.store.topups.gateway.iadadat_bwaba_aldfa', 'إعدادات بوّابة الدفع'))

@section('content')
    <x-page-header :title="setting('admin.store.topups.gateway.bwaba_aldfa', 'بوّابة الدفع')"
                   :subtitle="setting('admin.store.topups.gateway.alrsyd_ydaf_mn_alwyb_hwk_hsra_wrabt_alrjwa', 'الرصيد يُضاف من الويب هوك حصرًا — ورابط الرجوع للعرض فقط.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.store.topups.gateway.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.store.topups.gateway.tlbat_alshhn', 'طلبات الشحن'), 'url' => route('admin.topups.index')],
                       ['label' => setting('admin.store.topups.gateway.bwaba_aldfa', 'بوّابة الدفع')],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.topups.gateway.logs') }}" class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.store.topups.gateway.sjl_alwebhook_alkham', 'سجلّ الـWebhook الخام') }}</a>
            @if ($maySeeSecrets)
                <button type="button" id="test-connection" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.store.topups.gateway.akhtbar_alatsal', 'اختبار الاتّصال') }}</button>
            @endif
        </x-slot:action>
    </x-page-header>

    <div id="test-result" class="card p-3 mb-4 text-sm hidden"></div>

    <div class="card p-4 space-y-4">
        @foreach ($settings as $setting)
            @continue(in_array($setting->key, $secretKeys, true) && ! $maySeeSecrets)

            @include('admin.settings.partials.field', [
                'setting' => $setting,
                'registry' => $registry,
                'endpoint' => route('admin.topups.gateway.save'),
            ])
        @endforeach
    </div>

    @unless ($maySeeSecrets)
        {{-- 🔒 المفاتيح لمالك المنصّة وحده — ولا تظهر لغيره ولو مقنَّعة (12.2.1) --}}
        <p class="text-xs mt-3" style="color: var(--text-muted)">
            {{ setting('admin.store.topups.gateway.mfatyh_albwaba_mjmwaa_mhmya_bttdar_mn_hsab', 'مفاتيح البوّابة مجموعة محميّة — بتتدار من حساب مالك المنصّة.') }}
        </p>
    @endunless

    <div class="card p-4 mt-4 text-xs" style="color: var(--text-muted)">
        <p>{{ setting('admin.store.topups.gateway.alrsyd_bytdaf_mn_alwyb_hwk_whdh_walfatwra', 'الرصيد بيتضاف من الويب هوك وحده، والفاتورة الواحدة مش بتتحسب مرّتين مهما اتكرّر النداء.') }}</p>
        <p class="mt-1">{{ setting('admin.store.topups.gateway.alnda_bhash_khaty_bytsjl_wbytfrd_wsjl', 'النداء بهاش خاطئ بيتسجّل وبيتفرض — وسجلّ الـWebhook الخام بيوضّح نتيجة كلّ تحقّق.') }}</p>
    </div>

    @include('admin.settings.partials.autosave-script')
@endsection

@push('scripts')
@php
    /*
     | نصوص السكربت من الإعدادات (2.13-أ): لا حرفَ عربيّ داخل `<script>`،
     | فالمحروق هناك لا يصل لوحةَ الإدارة ولا الترجمة.
     */
    $jsText = [
        'testing' => setting('admin.store.topups.gateway.bnjrb_alatsal', 'بنجرّب الاتّصال…'),
    ];
@endphp

<script>
    const HC_GATEWAY_TEXT = @json($jsText);
(function () {
    var button = document.getElementById('test-connection');
    if (!button) { return; }

    var box = document.getElementById('test-result');
    var token = document.querySelector('meta[name="csrf-token"]');

    button.addEventListener('click', function () {
        box.classList.remove('hidden');
        box.textContent = HC_GATEWAY_TEXT.testing;

        fetch('{{ route('admin.topups.gateway.test') }}', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.getAttribute('content') : '' },
        }).then(function (r) { return r.json(); }).then(function (data) {
            box.textContent = data.message;
            box.style.color = data.ok ? 'var(--color-state-ok)' : 'var(--color-state-danger)';
        });
    });
})();
</script>
@endpush
