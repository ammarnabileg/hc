@extends('layouts.admin')

@section('title', setting('admin.dashboard.title', 'لوحة القيادة'))

@php
    /*
     | لقطة اللوحة لزرّ [استخراج كصورة] (12.14-هـ): نأخذ الكروت **كما تعرضها
     | الشاشة** — بلا حساب موازٍ ولا مصدر ثانٍ للرقم. والمكوّن مشتركٌ قائم.
     */
    $snapshotCards = $tab === 'details' ? ($details['cards'] ?? []) : ($kpis ?? []);
    $exportRows = collect($snapshotCards)->values()->map(fn ($card, $i) => [
        'rank' => $i + 1,
        'name' => (string) $card['label'],
        'value' => number_format($card['value']),
    ])->all();
@endphp

@section('content')
    <x-page-header :title="setting('admin.dashboard.title', 'لوحة القيادة')"
                   :subtitle="setting('admin.dashboard.subtitle', 'حالة المنصّة والقرارات المستنّياك')"
                   :breadcrumbs="[['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')], ['label' => 'لوحة القيادة']]">
        <x-slot:action>
            {{-- فعل رئيسيّ واحد بارز، والباقي في «⋯» (2.15-أ-2) --}}
            @if (\Illuminate\Support\Facades\Route::has('admin.users.approvals') && auth()->user()->allows('user_approvals.list'))
                <a href="{{ route('admin.users.approvals') }}"
                   class="btn hidden md:inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">راجع طلبات الاعتماد</a>
            @endif

            {{-- ⭐ [استخراج كصورة] بالمكوّن المشترك القائم لا بمكوّنٍ ثانٍ (12.14-هـ) --}}
            <x-export-image kind="stats" :title="setting('admin.dashboard.title', 'لوحة القيادة')"
                            :subtitle="$period['from']->format('Y/m/d').' — '.$period['to']->format('Y/m/d')"
                            :rows="$exportRows" />

            @if (count($quickActions) > 1 || $canCustomize)
                <details class="relative">
                    <summary class="list-none cursor-pointer rounded-xl px-3 py-2 text-sm select-none"
                             style="background: var(--surface-raised)" aria-label="اختصارات سريعة">⋯</summary>
                    <div class="card absolute end-0 mt-2 w-56 p-1 z-40">
                        @foreach ($quickActions as $action)
                            <a href="{{ $action['url'] }}" class="block rounded-lg px-3 py-2 text-sm motion-standard">{{ $action['label'] }}</a>
                        @endforeach

                        {{-- تخصيص اللوحة لكلّ دور (12.3-3) — لمن يملك تعديل الإعدادات وحده --}}
                        @if ($canCustomize)
                            <button type="button" data-modal-open="dashboard-layout"
                                    class="block w-full text-start rounded-lg px-3 py-2 text-sm motion-standard">تخصيص اللوحة</button>
                        @endif
                    </div>
                </details>
            @endif
        </x-slot:action>
    </x-page-header>

    @if (session('status'))
        <x-toast :message="session('status')" />
    @endif

    {{-- تنبيهات استباقيّة بعتباتها من الإعدادات (12.3-17) --}}
    @foreach ($alerts as $alert)
        <div class="card p-3 mb-3 flex items-center gap-2" role="status">
            <x-state-badge :state="$alert['state']" label="" />
            <span class="text-sm">{{ $alert['text'] }}</span>
        </div>
    @endforeach

    {{--
        فلتر الفترة العامّ **موحَّد مع 12.8**: «من/إلى» + مقارنة بالسابق (12.3-1/2).
        كان هنا `[1,7,30,90]` وهناك تاريخان — نفس المفهوم بشكلين فيتعلّمه الأدمن
        مرّتين. فصارت القائمة **اختصارات تملأ التاريخين** لا بديلًا عنهما.
    --}}
    <form method="get" class="card p-3 mb-4 flex flex-wrap items-end gap-3" data-dashboard-filters>
        <input type="hidden" name="tab" value="{{ $tab }}">

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">من</span>
            <input type="date" name="from" value="{{ $period['from']->toDateString() }}"
                   class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">إلى</span>
            <input type="date" name="to" value="{{ $period['to']->toDateString() }}"
                   class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="text-xs flex items-center gap-2 cursor-pointer pb-2">
            <input type="checkbox" name="compare" value="1" @checked($compare)>
            <span>مقارنة بالفترة السابقة</span>
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c; min-height: 44px">طبّق</button>

        <div class="flex flex-wrap items-center gap-2 basis-full">
            @foreach ($quickRanges as $range)
                <a href="{{ route('admin.dashboard', array_filter(['tab' => $tab, 'from' => $range['from'], 'to' => $range['to'], 'compare' => $compare ? 1 : null])) }}"
                   class="text-xs rounded-full px-3 py-1.5 motion-standard"
                   @if ($range['active']) aria-current="true" @endif
                   style="background: {{ $range['active'] ? 'color-mix(in srgb, var(--color-brand-500) 18%, transparent)' : 'var(--surface-sunken)' }}; color: {{ $range['active'] ? 'var(--color-brand-400)' : 'var(--text-muted)' }}">
                    {{ $range['active'] ? '● ' : '' }}{{ $range['label'] }}
                </a>
            @endforeach

            {{-- مؤشّر «آخر تحديث HH:MM» + التحديث التلقائيّ (12.3-5) --}}
            <span class="ms-auto text-xs" style="color: var(--text-muted)" data-dashboard-updated>
                آخر تحديث {{ $lastUpdated }}@if ($autoRefresh) · بيتحدّث كلّ {{ $refreshSeconds }} ثانية @endif
            </span>
        </div>
    </form>

    <x-tabs :tabs="$tabs" :current="$tab" />

    @if ($tab === 'details')
        @include('admin.dashboard.partials.details')
    @else
        @include('admin.dashboard.partials.overview')
    @endif

    @if ($canCustomize)
        @include('admin.dashboard.partials.customize')
    @endif
@endsection

@section('mobile_action')
    @if (\Illuminate\Support\Facades\Route::has('admin.users.approvals') && auth()->user()->allows('user_approvals.list'))
        <a href="{{ route('admin.users.approvals') }}"
           class="btn flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">راجع طلبات الاعتماد</a>
    @endif
@endsection

@if ($autoRefresh)
    @push('scripts')
        <script>
            /*
             | تحديث تلقائيّ للأرقام اللحظيّة (12.3-5): إعادة تحميل بفترةٍ من
             | الإعدادات. ويتوقّف حين تكون الصفحة مخفيّة أو الأدمن يكتب في حقل،
             | فلا يضيع مدخَلٌ تحت يده — ويستأنف عند العودة.
             */
            (function () {
                const seconds = {{ (int) $refreshSeconds }};
                if (!Number.isFinite(seconds) || seconds < 15) return;

                let timer = null;

                const busy = () => ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName || '');

                const arm = () => {
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        if (document.hidden || busy()) return arm();
                        window.location.reload();
                    }, seconds * 1000);
                };

                document.addEventListener('visibilitychange', arm);
                arm();
            })();
        </script>
    @endpush
@endif
