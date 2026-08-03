@extends('layouts.admin')

@section('title', 'إعدادات بوّابة الدفع')

@section('content')
    <x-page-header title="بوّابة الدفع"
                   subtitle="الرصيد يُضاف من الويب هوك حصرًا — ورابط الرجوع للعرض فقط."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'طلبات الشحن', 'url' => route('admin.topups.index')],
                       ['label' => 'بوّابة الدفع'],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.topups.gateway.logs') }}" class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">سجلّ الـWebhook الخام</a>
            @if ($maySeeSecrets)
                <button type="button" id="test-connection" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">اختبار الاتّصال</button>
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
            مفاتيح البوّابة مجموعة محميّة — بتتدار من حساب مالك المنصّة.
        </p>
    @endunless

    <div class="card p-4 mt-4 text-xs" style="color: var(--text-muted)">
        <p>الرصيد بيتضاف من الويب هوك وحده، والفاتورة الواحدة مش بتتحسب مرّتين مهما اتكرّر النداء.</p>
        <p class="mt-1">النداء بهاش خاطئ بيتسجّل وبيتفرض — وسجلّ الـWebhook الخام بيوضّح نتيجة كلّ تحقّق.</p>
    </div>

    @include('admin.settings.partials.autosave-script')
@endsection

@push('scripts')
<script>
(function () {
    var button = document.getElementById('test-connection');
    if (!button) { return; }

    var box = document.getElementById('test-result');
    var token = document.querySelector('meta[name="csrf-token"]');

    button.addEventListener('click', function () {
        box.classList.remove('hidden');
        box.textContent = 'بنجرّب الاتّصال…';

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
