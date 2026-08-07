@extends('layouts.admin')

@section('title', setting('admin.content.learning_settings.index.title', 'إعدادات التعلّم'))

@section('content')
    <x-page-header :title="setting('admin.content.learning_settings.index.title', 'إعدادات التعلّم')"
                   :subtitle="setting('admin.content.learning_settings.index.subtitle', 'الضبط العامّ لتجربة التعلّم والتعليقات والملاحظات ومؤثّراتها.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.content.learning_settings.index.crumb_1', 'إدارة التدريب'), 'url' => route('admin.paths.index')],
                       ['label' => setting('admin.content.learning_settings.index.title', 'إعدادات التعلّم')],
                   ]" />

    <div class="grid gap-4 md:grid-cols-[220px_1fr]">
        {{-- Side Nav لاصق بالمجموعات (24.4) — وعلى الموبايل رقائق أفقيّة --}}
        <nav class="flex md:flex-col gap-2 min-w-0 overflow-x-auto no-scrollbar md:sticky md:self-start" style="top: 80px">
            @foreach ($groups as $key => $meta)
                <a href="{{ route('admin.learning-settings.index', ['group' => $key]) }}"
                   class="shrink-0 rounded-xl px-3 py-2 text-sm motion-standard"
                   style="{{ $group === $key
                        ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                        : 'background: var(--surface-raised); color: var(--text)' }}">{{ $meta['label'] }}</a>
            @endforeach
        </nav>

        <div class="space-y-4">
            <div class="card p-4 flex items-center justify-between gap-3 flex-wrap">
                <p class="text-sm" style="color: var(--text-muted)">{{ $groups[$group]['hint'] }}</p>

                @can('learning_ux.manage')
                    {{-- إعادة الكلّ للافتراضيّ — بتأكيد قبل الطلب (24.4) --}}
                    <button type="button" class="text-xs underline shrink-0" data-learning-reset-group="{{ $group }}">
                        {{ setting('admin.content.learning_settings.index.reset_group', 'إعادة المجموعة للافتراضيّ') }}
                    </button>
                @endcan
            </div>

            <div class="card p-4 space-y-4">
                @forelse ($settings as $setting)
                    @include('admin.content.learning-settings.field', ['setting' => $setting, 'registry' => $registry, 'canEdit' => $canEdit])
                @empty
                    {{-- حالة «فارغة» رسميّة — لا حقلٌ مختلَق بلا قارئ حقيقيّ في الكود (2.13) --}}
                    <x-empty :message="setting('admin.content.learning_settings.index.empty', 'مجموعة فاضية — مفاتيحها لسّه بلا قارئ في الكود.')" />
                @endforelse
            </div>

            @unless ($canEdit)
                <p class="text-xs" style="color: var(--text-muted)">{{ setting('admin.content.learning_settings.index.read_only', 'عرض فقط — بلا صلاحيّة تعديل.') }}</p>
            @endunless
        </div>
    </div>
@endsection

@push('scripts')
@php
    $jsText = [
        'saved' => setting('admin.content.learning_settings.index.js_saved', 'تم الحفظ ✓'),
        'save_failed' => setting('admin.content.learning_settings.index.js_save_failed', 'مااتحفظش'),
        'reset_done' => setting('admin.content.learning_settings.index.js_reset_done', 'رجعت للافتراضيّ ✓'),
        'reset_group_confirm' => setting('admin.content.learning_settings.index.js_reset_group_confirm', 'هل تُرجِع كلّ إعدادات هذه المجموعة للافتراضيّ؟'),
    ];
@endphp
<script>
    const HC_LEARNING_SETTINGS_TEXT = @json($jsText);
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]');
        var CSRF = token ? token.getAttribute('content') : '';

        function post(url, payload) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            }).then(function (response) {
                return response.json().then(function (data) { return { ok: response.ok, data: data }; });
            });
        }

        function valueOf(input) {
            return input.type === 'checkbox' ? (input.checked ? 1 : 0) : input.value;
        }

        document.querySelectorAll('.learning-setting-row').forEach(function (row) {
            var input = row.querySelector('[data-learning-setting-input]');
            var status = row.querySelector('[data-learning-setting-status]');
            var key = row.getAttribute('data-setting');
            var timer = null;

            if (!input) { return; }

            function save() {
                status.textContent = '…';
                status.style.color = 'var(--text-muted)';

                post('{{ route('admin.learning-settings.field') }}', { key: key, value: valueOf(input) }).then(function (result) {
                    status.textContent = result.data.message || (result.ok ? HC_LEARNING_SETTINGS_TEXT.saved : HC_LEARNING_SETTINGS_TEXT.save_failed);
                    status.style.color = result.ok ? 'var(--color-state-ok)' : 'var(--color-state-warn)';
                    setTimeout(function () { status.textContent = ''; }, 2500);
                });
            }

            input.addEventListener('change', save);
            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(save, 700);
            });

            var reset = row.querySelector('[data-learning-setting-reset]');
            reset && reset.addEventListener('click', function () {
                post('{{ route('admin.learning-settings.reset') }}', { key: key }).then(function (result) {
                    if (result.data.value !== undefined && input.type !== 'checkbox') { input.value = result.data.value; }
                    if (input.type === 'checkbox') { input.checked = result.data.value === '1'; }
                    status.textContent = HC_LEARNING_SETTINGS_TEXT.reset_done;
                    setTimeout(function () { status.textContent = ''; }, 2500);
                });
            });
        });

        var resetGroupBtn = document.querySelector('[data-learning-reset-group]');
        resetGroupBtn && resetGroupBtn.addEventListener('click', function () {
            if (! confirm(HC_LEARNING_SETTINGS_TEXT.reset_group_confirm)) { return; }

            post('{{ route('admin.learning-settings.reset-group') }}', { group: resetGroupBtn.getAttribute('data-learning-reset-group') })
                .then(function () { window.location.reload(); });
        });
    })();
</script>
@endpush
