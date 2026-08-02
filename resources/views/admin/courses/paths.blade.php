@extends('layouts.app')

@section('title', 'المسارات')

@section('content')
    @php
        /** حمولة الصفّ لفورم البوب-أب — تُبنى هنا كي يبقى الجدول نظيفًا (2.15-أ-6) */
        $pathPayload = fn ($path) => json_encode([
            'name_ar' => $path->name_ar,
            'name_en' => $path->name_en,
            'description_ar' => $path->description_ar,
            'description_en' => $path->description_en,
            'sort_order' => $path->sort_order,
            'status' => $path->status,
            'forced_order' => (bool) $path->forced_order,
            'exam_price_coins' => (float) ($examPrices[$path->id] ?? $path->exam_price_coins),
            'url' => route('admin.paths.update', $path),
        ], JSON_UNESCAPED_UNICODE);
    @endphp

    {{-- المسارات (12.4-أ · 24.1): تنظيم التدريبات في مسارات مرتّبة قابلة للنشر --}}
    <x-page-header
        title="المسارات"
        subtitle="رتّب تدريباتك في مسارات — وحذف المسار ما بيحذفش تدريباته."
        :breadcrumbs="[['label' => 'لوحة الإدارة', 'url' => route('admin.courses.index')], ['label' => 'المسارات']]">
        <x-slot:action>
            @can('paths.create')
                <button type="button" data-modal-open="path-form" data-path-new
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">+ مسار جديد</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    @include('admin.courses.partials.nav', ['current' => 'paths'])

    @if ($paths->count() >= (int) setting('ux.filters.min_rows', 10))
        {{-- بلا فلاتر تحت العشرة صفوف — بحث فقط (2.15-ب) --}}
        <x-filters :action="route('admin.paths.index')">
            <label class="block flex-1 min-w-[12rem]">
                <span class="block text-sm mb-1">بحث</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="اسم المسار…"
                       class="w-full rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
            <label class="block">
                <span class="block text-sm mb-1">الحالة</span>
                <select name="status" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">الكلّ</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">تصفية</button>
        </x-filters>
    @else
        <form method="get" class="mb-4">
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="ابحث باسم المسار…"
                   class="w-full md:w-80 rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </form>
    @endif

    @if ($paths->isEmpty())
        <x-empty message="لسّه بدري — أضِف مسارك الأوّل." />
    @else
        {{-- ديسكتوب: جدول قابل لسحب الصفوف للترتيب --}}
        <div class="hidden md:block card overflow-hidden">
            <table class="w-full text-sm">
                <thead style="background: var(--surface-sunken)">
                    <tr class="text-start">
                        <th class="p-3 w-8"></th>
                        <th class="p-3 text-start">المسار</th>
                        <th class="p-3 text-start">عدد التدريبات</th>
                        <th class="p-3 text-start">سعر امتحان الشهادة</th>
                        <th class="p-3 text-start">الحالة</th>
                        <th class="p-3 text-start">الترتيب</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody data-sortable="{{ route('admin.paths.reorder') }}">
                    @foreach ($paths as $path)
                        <tr data-sort-id="{{ $path->id }}" style="border-top: 1px solid var(--border)">
                            <td class="p-3 cursor-grab select-none" aria-label="مقبض السحب">⠿</td>
                            <td class="p-3">
                                <div class="font-semibold">{{ $path->name_ar }}</div>
                                @if ($path->name_en)
                                    <div class="text-xs" style="color: var(--text-muted)">{{ $path->name_en }}</div>
                                @endif
                            </td>
                            <td class="p-3">
                                {{-- الضغط على العدد ⟵ إدارة تدريبات المسار (12.4-أ) --}}
                                <a href="{{ route('admin.paths.courses', $path) }}" class="underline"
                                   style="color: var(--color-brand-400)">{{ $path->courses_count }} تدريب</a>
                            </td>
                            {{-- السعر من صفّ الامتحان — مصدر الحقيقة الواحد (12.4-أ) --}}
                            <td class="p-3">{{ (int) ($examPrices[$path->id] ?? $path->exam_price_coins) }} كوينز</td>
                            <td class="p-3">
                                <x-state-badge :state="$path->status === 'published' ? 'ok' : ($path->status === 'archived' ? 'idle' : 'warn')"
                                               :label="$statuses[$path->status] ?? $path->status" />
                            </td>
                            <td class="p-3">{{ $path->sort_order }}</td>
                            <td class="p-3 text-end">
                                <details class="relative inline-block">
                                    <summary class="cursor-pointer list-none px-2" aria-label="إجراءات">⋯</summary>
                                    <div class="card absolute end-0 mt-1 p-2 w-56 z-20 text-start space-y-1">
                                        @can('paths.edit')
                                            <button type="button" class="block w-full text-start px-2 py-1 rounded-lg text-sm"
                                                    data-modal-open="path-form"
                                                    data-path="{{ $pathPayload($path) }}">تعديل</button>
                                        @endcan
                                        <a href="{{ route('admin.paths.courses', $path) }}"
                                           class="block px-2 py-1 rounded-lg text-sm">إدارة تدريباته</a>
                                        @can('paths.delete')
                                            <form method="post" action="{{ route('admin.paths.destroy', $path) }}"
                                                  onsubmit="return confirm('{{ setting('paths.delete.confirm_text', 'هنشيل المسار — وتدريباته هتفضل زيّ ما هي. نكمّل؟') }}')">
                                                @csrf @method('delete')
                                                <button class="block w-full text-start px-2 py-1 rounded-lg text-sm"
                                                        style="color: var(--color-state-danger)">حذف</button>
                                            </form>
                                        @endcan
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- موبايل: كروت رأسيّة بلا تمرير أفقيّ + أزرار ترتيب بدل السحب (2.15-ج) --}}
        <div class="md:hidden space-y-3" data-sortable="{{ route('admin.paths.reorder') }}">
            @foreach ($paths as $path)
                <div class="card p-4" data-sort-id="{{ $path->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-semibold truncate">{{ $path->name_ar }}</div>
                            <a href="{{ route('admin.paths.courses', $path) }}" class="text-sm underline"
                               style="color: var(--color-brand-400)">{{ $path->courses_count }} تدريب</a>
                        </div>
                        <x-state-badge :state="$path->status === 'published' ? 'ok' : 'warn'"
                                       :label="$statuses[$path->status] ?? $path->status" />
                    </div>
                    <details class="mt-3">
                        <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">تفاصيل أكتر</summary>
                        <div class="mt-2 text-sm space-y-1">
                            <div>سعر امتحان الشهادة: {{ (int) ($examPrices[$path->id] ?? $path->exam_price_coins) }} كوينز</div>
                            <div>ترتيب المشاهدة: {{ $path->forced_order ? 'إجباريّ' : 'حرّ' }}</div>
                        </div>
                    </details>
                    <div class="mt-3 flex gap-2">
                        <button type="button" data-sort-up class="btn rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken)" aria-label="فوق">↑</button>
                        <button type="button" data-sort-down class="btn rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken)" aria-label="تحت">↓</button>
                        <a href="{{ route('admin.paths.courses', $path) }}"
                           class="btn rounded-xl px-3 py-2 text-sm flex-1 text-center"
                           style="background: var(--surface-raised)">تدريباته</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- فورم المسار في بوب-أب — التفاصيل بلا مغادرة القائمة (2.15-أ-6) --}}
    @can('paths.create')
        <x-modal id="path-form" title="مسار">
            <form method="post" action="{{ route('admin.paths.store') }}" data-path-form class="space-y-4">
                @csrf
                <input type="hidden" name="_method" value="post" data-path-method>

                <div class="grid md:grid-cols-2 gap-3">
                    <x-form.input name="name_ar" label="الاسم (عربيّ)" required />
                    <x-form.input name="name_en" label="الاسم (إنجليزيّ)" />
                </div>

                <label class="block">
                    <span class="block text-sm mb-1">الوصف (عربيّ)</span>
                    <textarea name="description_ar" rows="2" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <div class="grid md:grid-cols-3 gap-3">
                    <x-form.input name="sort_order" label="الترتيب" type="number" value="0" />
                    <label class="block">
                        <span class="block text-sm mb-1">الحالة</span>
                        <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    {{-- سعر امتحان شهادة المسار بالكوينز (12.4-أ) --}}
                    <x-form.input name="exam_price_coins" label="سعر امتحان الشهادة (كوينز)" type="number"
                                  :value="setting('paths.exam.default_price_coins', 0)" />
                </div>

                {{-- ⭐ إعداد ترتيب المشاهدة: إجباريّ أم حرّ/عشوائيّ (12.4-أ) --}}
                <fieldset class="card p-3">
                    <legend class="text-sm px-1">ترتيب المشاهدة</legend>
                    <label class="flex items-center gap-2 text-sm mt-2">
                        <input type="radio" name="forced_order" value="1"> إجباريّ بالترتيب
                    </label>
                    <label class="flex items-center gap-2 text-sm mt-1">
                        <input type="radio" name="forced_order" value="0" checked> حرّ — يقدر يشوف التدريب التاني قبل الأوّل
                    </label>
                </fieldset>

                <details>
                    <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">خيارات متقدّمة</summary>
                    <div class="mt-3 space-y-3">
                        <x-form.input name="cover_path" label="مسار الغلاف"
                                      hint="اختر من مكتبة الوسائط أو ارفع جديدًا ثمّ الصق المسار." />
                        <x-form.input name="description_en" label="الوصف (إنجليزيّ)" />
                    </div>
                </details>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">حفظ</button>
            </form>
        </x-modal>
    @endcan

    @include('admin.courses.partials.sortable')
    @include('admin.courses.partials.toast')

    @push('scripts')
        <script>
            /* تعبئة فورم المسار من زرّ التعديل — بلا صفحة جديدة (2.15-أ-6) */
            const pathForm = document.querySelector('[data-path-form]');
            const pathStoreUrl = @json(route('admin.paths.store'));

            document.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-path], [data-path-new]');
                if (!btn || !pathForm) return;

                const data = btn.dataset.path ? JSON.parse(btn.dataset.path) : null;
                pathForm.action = data?.url || pathStoreUrl;
                pathForm.querySelector('[data-path-method]').value = data ? 'put' : 'post';

                ['name_ar', 'name_en', 'description_ar', 'description_en', 'sort_order', 'exam_price_coins'].forEach((key) => {
                    const field = pathForm.querySelector(`[name="${key}"]`);
                    if (field) field.value = data?.[key] ?? '';
                });

                const status = pathForm.querySelector('[name="status"]');
                if (status) status.value = data?.status ?? 'draft';

                pathForm.querySelectorAll('[name="forced_order"]').forEach((radio) => {
                    radio.checked = String(Number(data?.forced_order ?? 0)) === radio.value;
                });
            });
        </script>
    @endpush
@endsection

@section('mobile_action')
    @can('paths.create')
        <button type="button" data-modal-open="path-form" data-path-new
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">+ مسار جديد</button>
    @endcan
@endsection
