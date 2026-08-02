@extends('layouts.app')

@section('title', 'الإدارة المركزيّة للتطوّع')

@section('content')
    <x-page-header
        title="الإدارة المركزيّة للتطوّع"
        subtitle="مكان واحد يضبط أرقام منظومة التطوّع كلّها — ومنه محتوى صفحة التطوّع التعريفيّة."
        :breadcrumbs="[['label' => 'لوحة الإدارة', 'url' => url('/admin')], ['label' => 'التطوّع']]">
        <x-slot:action>
            @can('volunteer_page.edit')
                <button type="button" data-modal-open="block-modal"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">+ كتلة محتوى</button>
            @endcan
            <details class="relative">
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm select-none" style="background: var(--surface-raised)" aria-label="خيارات أخرى">⋯</summary>
                <div class="card absolute inset-inline-end-0 mt-2 w-60 p-2 text-sm z-30">
                    @can('org_chart.view')<a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('admin.volunteer.org') }}">الهيكل والبوزشنز والسعة</a>@endcan
                    @can('rep_transactions.view')<a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('admin.volunteer.rep') }}">ضبط Rep</a>@endcan
                    @can('offboarding.view')<a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('admin.volunteer.offboarding') }}">الأوفبوردنج</a>@endcan
                    @can('volunteer_certificates.view')<a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('admin.volunteer.certificates') }}">شهادات التطوّع</a>@endcan
                    @can('reports_volunteer.view')<a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('admin.volunteer.analytics') }}">تحليلات التطوّع</a>@endcan
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    @include('admin.volunteer.partials.tabs', ['current' => 'index'])

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <x-kpi label="متطوّعون نشطون" :value="$kpis['active']" icon="contribution" />
        <x-kpi label="شواغر" :value="$kpis['vacancies']" icon="placement" />
        <x-kpi label="تجاوزات نطاق الإشراف" :value="$kpis['overflows']" icon="warning"
               hint="مؤشّر لا مانع — السعة لا تُوقِف تسكينًا ولا ترقية." />
        <x-kpi label="خروج آخر {{ $days }} يومًا" :value="$kpis['exits']" icon="exit" />
    </section>

    <div class="grid lg:grid-cols-3 gap-4 mt-4">
        {{-- التنبيهات --}}
        <section class="card p-4 md:p-5">
            <h2 class="font-bold mb-3">تنبيهات تحتاج نظرة</h2>
            <ul class="space-y-2 text-sm">
                <li class="flex items-center justify-between gap-2">
                    <span>كيانات غير صحّيّة</span>
                    <x-state-badge :state="$alerts['unhealthy'] ? 'warn' : 'ok'" :label="$alerts['unhealthy'].' كيان'" />
                </li>
                <li class="flex items-center justify-between gap-2">
                    <span>تجاوز نطاق الإشراف</span>
                    <x-state-badge :state="$alerts['overflows'] ? 'danger' : 'ok'" :label="$alerts['overflows'].' حالة'" />
                </li>
                <li class="flex items-center justify-between gap-2">
                    <span>ملفّات إنهاء مفتوحة</span>
                    <x-state-badge :state="$alerts['pendingExits'] ? 'warn' : 'ok'" :label="$alerts['pendingExits'].' ملفّ'" />
                </li>
                <li class="flex items-center justify-between gap-2">
                    <span>مستحقّ شهادة ولم تُصدَر</span>
                    <x-state-badge :state="$alerts['pendingCertificates'] ? 'warn' : 'ok'" :label="$alerts['pendingCertificates'].' متطوّع'" />
                </li>
            </ul>
        </section>

        {{-- آخر التسكينات --}}
        <section class="card p-4 md:p-5">
            <h2 class="font-bold mb-3">آخر التسكينات</h2>
            @forelse ($recentPlacements as $membership)
                <div class="flex items-center justify-between gap-2 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                    <div class="min-w-0">
                        <div class="truncate font-semibold">{{ $membership->user?->name }}</div>
                        <div class="text-xs" style="color: var(--text-muted)">{{ $membership->position?->name_ar }} · {{ $membership->entity?->name_ar }}</div>
                    </div>
                    <span class="text-xs shrink-0" style="color: var(--text-muted)"
                          title="{{ $membership->started_at?->format('Y-m-d') }}">{{ $membership->started_at?->diffForHumans() }}</span>
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted)">لسّه بدري — أوّل تسكين مستنّيك.</p>
            @endforelse
        </section>

        {{-- الأحمال --}}
        <section class="card p-4 md:p-5">
            <h2 class="font-bold mb-3">أعلى الأحمال (مهامّ مفتوحة)</h2>
            @forelse ($loads as $row)
                <div class="flex items-center justify-between gap-2 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                    <span class="truncate">{{ $row['membership']->user?->name }}</span>
                    <span class="flex items-center gap-2">
                        <span class="font-bold">{{ $row['open'] }}</span>
                        <x-state-badge :state="$row['state']" :label="$row['cap'] ? 'سقف '.$row['cap'] : 'بلا سقف'" />
                    </span>
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-muted)">مفيش مهامّ مفتوحة دلوقتي.</p>
            @endforelse
        </section>
    </div>

    {{-- ⭐ صفحة التطوّع التعريفيّة: كامل محتواها يُدار من هنا (13.4-أ) --}}
    <section class="card p-4 md:p-5 mt-4">
        <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
            <h2 class="font-bold">صفحة التطوّع التعريفيّة</h2>
            <span class="text-xs" style="color: var(--text-muted)">{{ count($blocks) }} كتلة محتوى</span>
        </div>

        @if ($blocks)
            {{-- الجداول كروت رأسيّة على الموبايل بلا تمرير أفقيّ (2.15-ج) --}}
            <div class="space-y-2">
                @foreach ($blocks as $i => $block)
                    <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
                        <div class="flex items-start justify-between gap-3 flex-wrap">
                            <div class="min-w-0">
                                <div class="text-xs" style="color: var(--text-muted)">{{ $blockTypes[$block['type']] ?? $block['type'] }}</div>
                                <div class="font-semibold">{{ $block['title'] }}</div>
                                <p class="text-sm mt-1" style="color: var(--text-muted)">{{ \Illuminate\Support\Str::limit($block['body'], 140) }}</p>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                @can('volunteer_page.edit')
                                    <button type="button" class="text-xs underline"
                                            data-block-edit
                                            data-index="{{ $i }}"
                                            data-type="{{ $block['type'] }}"
                                            data-title="{{ $block['title'] }}"
                                            data-body="{{ $block['body'] }}">تعديل</button>
                                @endcan
                                @can('volunteer_page.manage')
                                    <form method="post" action="{{ route('admin.volunteer.page.block.delete') }}"
                                          onsubmit="return confirm('تحذف الكتلة دي؟ الفعل ده مالوش رجوع.')">
                                        @csrf
                                        <input type="hidden" name="index" value="{{ $i }}">
                                        <button type="submit" class="text-xs underline" style="color: var(--color-state-danger)">حذف</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <x-empty :message="setting('volunteer_page.empty_message', 'لسّه محتوى الصفحة فاضي — ابدأ بأوّل كتلة.')" />
        @endif
    </section>

    @can('volunteer_page.edit')
        @include('admin.volunteer.partials.settings-card', [
            'title' => 'حقول صفحة التطوّع (العنوان · الميثاق · الإحصائيّات)',
            'rows' => $page,
            'action' => route('admin.volunteer.page.save'),
            'resetAction' => route('admin.volunteer.reset', 'volunteer_page'),
        ])
    @endcan

    {{-- 🔒 العنصر الشرفيّ «أخوكم» — لمالك المنصّة وحده، ولا يراه غيره أصلًا (13.4-ص-د · 2.15-أ-7) --}}
    @owner
        @include('admin.volunteer.partials.honorary-card')
    @endowner
@endsection

@section('mobile_action')
    @can('volunteer_page.edit')
        <button type="button" data-modal-open="block-modal"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">+ كتلة محتوى</button>
    @endcan
@endsection

@push('modals')
    <x-modal id="block-modal" title="كتلة محتوى في صفحة التطوّع">
        <form method="post" action="{{ route('admin.volunteer.page.block.save') }}" id="block-form">
            @csrf
            <input type="hidden" name="index" id="block-index" value="">

            <label class="block text-sm font-semibold mb-1" for="block-type">النوع</label>
            <select name="type" id="block-type" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($blockTypes as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>

            <label class="block text-sm font-semibold mb-1" for="block-title">العنوان</label>
            <input type="text" name="title" id="block-title" required maxlength="180"
                   class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

            <label class="block text-sm font-semibold mb-1" for="block-body">النصّ</label>
            <textarea name="body" id="block-body" rows="5" required maxlength="4000"
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>

            <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">احفظ الكتلة</button>
        </form>
    </x-modal>
@endpush

@push('scripts')
    <script>
        // فتح البوب-أب محمَّلًا ببيانات الكتلة — والمستخدم لا يفقد مكانه (2.15-أ-6)
        document.querySelectorAll('[data-block-edit]').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById('block-index').value = btn.dataset.index;
                document.getElementById('block-type').value = btn.dataset.type;
                document.getElementById('block-title').value = btn.dataset.title;
                document.getElementById('block-body').value = btn.dataset.body;
                const modal = document.getElementById('block-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        });
    </script>
@endpush
