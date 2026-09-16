@extends('layouts.admin')

@section('title', setting('admin.trash.page_title', 'سلّة المحذوفات'))

@section('content')
    <x-page-header :title="setting('admin.trash.page_title', 'سلّة المحذوفات')"
                   :subtitle="setting('admin.trash.page_subtitle', 'كلّ عنصر اتحذف مبدئيًّا عبر المنصّة، في مكان واحد.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.trash.breadcrumb_admin', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.trash.page_title', 'سلّة المحذوفات')],
                   ]" />

    {{-- ثلاث كروت KPI بحدّ أقصى أربعة (2.15-أ-3) --}}
    <div class="grid gap-3 grid-cols-2 lg:grid-cols-3 mb-4">
        <x-kpi :label="setting('admin.trash.kpi_total', 'إجماليّ المحذوف')" :value="$counts['total']" icon="trash" />
        <x-kpi :label="setting('admin.trash.kpi_expiring', 'قربت مهلته تخلص (≤3 أيّام)')" :value="$counts['expiring_soon']" icon="clock" />
        <x-kpi :label="setting('admin.trash.kpi_closed', 'المهلة خلصت: جاهز للحذف النهائيّ')" :value="$counts['window_closed']" icon="lock" />
    </div>

    {{-- فلتر المورد — رقائق أفقيّة على الموبايل بلا تمرير أفقيّ للصفحة كلّها (2.15-ج) --}}
    <x-tabs :current="$type ?? 'all'" :tabs="collect(['all' => setting('admin.trash.filter_all', 'الكلّ')] + $resources)
        ->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'url' => route('admin.ops.trash', $key === 'all' ? [] : ['type' => $key]),
        ])->values()->all()" />

    <p class="text-xs mb-3" style="color: var(--text-muted)">
        {{ strtr((string) setting('admin.trash.retention_hint', 'العنصر قابلٌ للاسترجاع خلال :days يومًا من حذفه، وبعدها يبقى قابلًا للحذف النهائيّ فقط.'), [':days' => (string) $retentionDays]) }}
    </p>

    @if ($rows->isEmpty())
        <x-empty :message="setting('admin.trash.empty_text', 'السلّة فاضية. مفيش حاجة اتحذفت لسّه.')" :filtered="$type !== null" />
    @else
        @include('admin.trash.partials.table')
        <div class="mt-5">{{ $rows->links() }}</div>
    @endif

    {{-- التفاصيل في بانل جانبيّ لا صفحة جديدة (2.15-أ-6) --}}
    <div data-trash-panel class="hidden fixed inset-0 z-40" role="dialog" aria-modal="true"
         aria-label="{{ setting('admin.trash.panel_aria', 'تفاصيل العنصر المحذوف') }}">
        <div class="absolute inset-0" style="background: rgba(0,0,0,.5)" data-trash-close></div>
        <aside class="absolute inset-y-0 end-0 w-full max-w-md overflow-y-auto"
               style="background: var(--surface-raised); border-inline-start: 1px solid var(--border)">
            <button type="button" data-trash-close class="m-3 rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken)">{{ setting('admin.trash.close_label', 'إغلاق') }}</button>
            <div data-trash-body class="text-sm">
                <p class="p-4" style="color: var(--text-muted)">{{ setting('admin.trash.loading_text', 'بنجيب التفاصيل…') }}</p>
            </div>
        </aside>
    </div>

    @php
        // نصوص السكربت من الإعدادات (2.13-أ) — لا حرفَ عربيّ محروق داخل <script>
        $jsText = [
            'loading' => setting('admin.trash.loading_text', 'بنجيب التفاصيل…'),
            'open_failed' => setting('admin.trash.open_failed', 'تعذّر فتح العنصر. جرّب تاني أو حدّث الصفحة.'),
            'network_failed' => setting('admin.trash.network_failed', 'تعذّر الاتصال. راجع الشبكة وجرّب تاني.'),
        ];
    @endphp

    <script>
        const HC_TRASH_TEXT = @json($jsText);
        (() => {
            const panel = document.querySelector('[data-trash-panel]');
            const body = panel?.querySelector('[data-trash-body]');
            if (!panel || !body) return;

            const close = () => panel.classList.add('hidden');

            document.querySelectorAll('[data-trash-row]').forEach((row) => {
                row.addEventListener('click', async () => {
                    panel.classList.remove('hidden');
                    body.innerHTML = '<p class="p-4">' + HC_TRASH_TEXT.loading + '</p>';

                    try {
                        const res = await fetch(row.dataset.url, { headers: { Accept: 'text/html' } });
                        body.innerHTML = res.ok
                            ? await res.text()
                            : '<p class="p-4">' + HC_TRASH_TEXT.open_failed + '</p>';
                    } catch (e) {
                        body.innerHTML = '<p class="p-4">' + HC_TRASH_TEXT.network_failed + '</p>';
                    }
                });
            });

            panel.querySelectorAll('[data-trash-close]').forEach((el) => el.addEventListener('click', close));
            document.addEventListener('keydown', (e) => e.key === 'Escape' && close());
        })();
    </script>
@endsection
