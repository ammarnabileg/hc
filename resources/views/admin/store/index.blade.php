@extends('layouts.admin')

@section('title', 'المتجر والماليّات')

@section('content')
    <x-page-header title="المتجر والماليّات"
                   subtitle="كلّ ما يُباع وكلّ ما يُحصَّل — في مكان واحد."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'المتجر والماليّات'],
                   ]">
        <x-slot:action>
            {{-- فعل رئيسيّ واحد بارز، والباقي في «⋯» (2.15-أ-2) --}}
            <button type="button" data-modal-open="new-item"
                    class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">+ عنصر جديد</button>

            <details class="relative">
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm" style="background: var(--surface-raised)">⋯</summary>
                <div class="absolute end-0 mt-2 w-56 card p-2 z-20 text-sm space-y-1">
                    <a class="block px-2 py-1 rounded hover:opacity-80" href="{{ route('admin.topups.index') }}">طلبات الشحن</a>
                    <a class="block px-2 py-1 rounded hover:opacity-80" href="{{ route('admin.topups.methods') }}">طرق التحويل والعروض</a>
                    @if ($financeVisible)
                        {{-- 🔒 مجموعة معزولة: لا تظهر أصلًا لغير مالك المنصّة (12.2.1) --}}
                        <a class="block px-2 py-1 rounded hover:opacity-80" href="{{ route('admin.finance.index') }}"><x-icon name="lock" size="16" /> الماليّات</a>
                    @endif
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid gap-3 grid-cols-2 lg:grid-cols-4 mb-4">
        @foreach ($kpis as $kpi)
            <x-kpi :label="$kpi['label']" :value="$kpi['value']" :icon="$kpi['icon']" />
        @endforeach
    </div>

    <x-tabs :current="$tab" :tabs="collect($tabs)->map(fn ($t, $key) => [
        'key' => $key,
        'label' => $t['label'],
        'url' => route('admin.store.index', ['tab' => $key]),
    ])->values()->all()" />

    @include('admin.store.partials.filters')

    @if ($tab === 'orders')
        {{-- ⛔ قاعدة ثابتة تُعرَض دائمًا: لا استرجاع نقديّ (19.4) — والتصحيح التقنيّ وحده البديل --}}
        <p class="text-xs mb-3" style="color: var(--text-muted)">
            مافيش استرجاع نقديّ — الرصيد يفضل في محفظة صاحبه، والخطأ التقنيّ يتصحَّح بمعاملة موثّقة بمرجعها.
        </p>
    @elseif ($tab === 'library')
        {{-- التحليلات مجمّعة فقط — ولا سجلّ فتح فرديّ لأيّ ملفّ (مرفوض صراحةً في 20.5) --}}
        <p class="text-xs mb-3" style="color: var(--text-muted)">
            التحليلات مجمّعة فقط (الأكثر قراءةً · متوسّط الإكمال) — مافيش سجلّ فتح فرديّ لأيّ مستخدم.
        </p>
    @endif

    @if ($rows->isEmpty())
        <x-empty message="مفيش حاجة هنا لسه — ابدأ بأوّل عنصر." action="+ عنصر جديد" :href="route('admin.store.index', ['tab' => $tab])" />
    @else
        @include('admin.store.partials.table-' . $tab)
        <div class="mt-5">{{ $rows->links() }}</div>
    @endif

    @include('admin.store.partials.new-item-modal')
@endsection

@section('mobile_action')
    <button type="button" data-modal-open="new-item"
            class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
            style="background: var(--color-brand-500); color: #04201c">+ عنصر جديد</button>
@endsection
