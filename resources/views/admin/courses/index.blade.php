@extends('layouts.app')

@section('title', 'التدريبات')

@section('content')
    {{-- التدريبات (12.4-ب · 24.1) --}}
    <x-page-header
        title="التدريبات"
        subtitle="أنشئ التدريب وسعّره وأتِحه وابنِ محتواه."
        :breadcrumbs="[['label' => 'إدارة التدريب', 'url' => route('admin.paths.index')], ['label' => 'التدريبات']]">
        <x-slot:action>
            @can('courses.create')
                <a href="{{ route('admin.courses.create') }}"
                   class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">+ تدريب جديد</a>
            @endcan
        </x-slot:action>
    </x-page-header>

    @include('admin.courses.partials.nav', ['current' => 'courses'])

    {{-- ثلاثة فلاتر ظاهرة + بحث، والباقي مطويّ (2.15-أ-4) --}}
    <x-filters :action="route('admin.courses.index')">
        <label class="block flex-1 min-w-[12rem]">
            <span class="block text-sm mb-1">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="اسم التدريب أو الشهادة…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
        <label class="block">
            <span class="block text-sm mb-1">المسار</span>
            <select name="path" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($paths as $path)
                    <option value="{{ $path->id }}" @selected($filters['path'] === $path->id)>{{ $path->name_ar }}</option>
                @endforeach
            </select>
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

        <x-slot:advanced>
            <label class="block">
                <span class="block text-sm mb-1">التسعير</span>
                <select name="pricing" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">الكلّ</option>
                    <option value="free" @selected($filters['pricing'] === 'free')>مجّانيّ</option>
                    <option value="paid" @selected($filters['pricing'] === 'paid')>مدفوع</option>
                </select>
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($courses->isEmpty())
        <x-empty message="لسّه مفيش تدريبات — ابدأ بأوّل واحد."
                 :action="auth()->user()->can('courses.create') ? 'تدريب جديد' : null"
                 :href="route('admin.courses.create')" />
    @else
        <form method="post" action="{{ route('admin.courses.bulk') }}" data-bulk-form>
            @csrf

            {{-- ⭐ الإجراء الجماعيّ يظهر عند الاختيار فقط، ومخفيّ تمامًا قبله (2.15-ب) --}}
            @can('courses.edit')
                <div class="card p-3 mb-3 hidden items-center gap-3 flex-wrap" data-bulk-bar>
                    <span class="text-sm"><span data-bulk-count>0</span> مختار</span>
                    <select name="action" class="rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="publish">نشر</option>
                        <option value="hide">إخفاء</option>
                        <option value="archive">أرشفة</option>
                        <option value="move_path">نقل لمسار</option>
                        <option value="price">تسعير دفعة</option>
                    </select>
                    <select name="path_id" class="rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="">— المسار —</option>
                        @foreach ($paths as $path)
                            <option value="{{ $path->id }}">{{ $path->name_ar }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="price_coins" min="0" placeholder="السعر بالكوينز"
                           class="rounded-xl px-3 py-2 text-sm w-40"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">نفّذ</button>
                </div>
            @endcan

            {{-- ديسكتوب: جدول بأعمدته الافتراضيّة (5–7) --}}
            <div class="hidden md:block card overflow-hidden">
                <table class="w-full text-sm">
                    <thead style="background: var(--surface-sunken)">
                        <tr>
                            <th class="p-3 w-8"><input type="checkbox" data-bulk-all aria-label="اختيار الكلّ"></th>
                            <th class="p-3 text-start">اسم العرض</th>
                            <th class="p-3 text-start">اسم الشهادة</th>
                            <th class="p-3 text-start">المسار(ات)</th>
                            <th class="p-3 text-start">سيكشنز/دروس</th>
                            <th class="p-3 text-start">السعر</th>
                            <th class="p-3 text-start">المسجّلون</th>
                            <th class="p-3 text-start">الحالة</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($courses as $course)
                            <tr style="border-top: 1px solid var(--border)">
                                <td class="p-3"><input type="checkbox" name="ids[]" value="{{ $course->id }}" data-bulk-item></td>
                                <td class="p-3 font-semibold">{{ $course->name_ar }}</td>
                                <td class="p-3">{{ $course->cert_name_ar ?: '—' }}</td>
                                <td class="p-3">{{ implode('، ', $pathNames[$course->id] ?? []) ?: '—' }}</td>
                                <td class="p-3">{{ $counts['sections'][$course->id] ?? 0 }} / {{ $counts['lessons'][$course->id] ?? 0 }}</td>
                                <td class="p-3">{{ $course->is_free ? 'مجّانيّ' : (int) $course->price_coins }}</td>
                                <td class="p-3">
                                    {{-- الضغط على العدد ⟵ مَن هم (12.4-ب) --}}
                                    <a href="{{ route('admin.courses.enrollees', $course) }}" class="underline"
                                       style="color: var(--color-brand-400)">{{ $counts['enrollments'][$course->id] ?? 0 }}</a>
                                </td>
                                <td class="p-3">
                                    <x-state-badge :state="$course->status === 'published' ? 'ok' : ($course->status === 'archived' ? 'idle' : 'warn')"
                                                   :label="$statuses[$course->status] ?? $course->status" />
                                </td>
                                <td class="p-3 text-end">
                                    <details class="relative inline-block">
                                        <summary class="cursor-pointer list-none px-2" aria-label="إجراءات">⋯</summary>
                                        <div class="card absolute end-0 mt-1 p-2 w-52 z-20 text-start space-y-1">
                                            @can('courses.edit')
                                                <a href="{{ route('admin.courses.edit', $course) }}" class="block px-2 py-1 text-sm">تعديل</a>
                                            @endcan
                                            <a href="{{ route('admin.courses.stats', $course) }}" class="block px-2 py-1 text-sm">إحصائيّات</a>
                                            <a href="{{ route('admin.courses.preview', $course) }}" class="block px-2 py-1 text-sm">معاينة كطالب</a>
                                            <a href="{{ route('admin.courses.audit', $course) }}" class="block px-2 py-1 text-sm">سجلّ التدقيق</a>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- موبايل: كروت رأسيّة بأهمّ 3 حقول والباقي بالتوسيع (2.15-ج) --}}
            <div class="md:hidden space-y-3">
                @foreach ($courses as $course)
                    <div class="card p-4">
                        <div class="flex items-start gap-2">
                            <input type="checkbox" name="ids[]" value="{{ $course->id }}" data-bulk-item class="mt-1">
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold truncate">{{ $course->name_ar }}</div>
                                <div class="text-xs mt-1" style="color: var(--text-muted)">
                                    {{ $course->is_free ? 'مجّانيّ' : (int) $course->price_coins.' كوينز' }} ·
                                    <a href="{{ route('admin.courses.enrollees', $course) }}" class="underline">
                                        {{ $counts['enrollments'][$course->id] ?? 0 }} مسجّل
                                    </a>
                                </div>
                            </div>
                            <x-state-badge :state="$course->status === 'published' ? 'ok' : 'warn'"
                                           :label="$statuses[$course->status] ?? $course->status" />
                        </div>
                        <details class="mt-3">
                            <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">تفاصيل أكتر</summary>
                            <div class="mt-2 text-sm space-y-1">
                                <div>اسم الشهادة: {{ $course->cert_name_ar ?: '—' }}</div>
                                <div>المسارات: {{ implode('، ', $pathNames[$course->id] ?? []) ?: '—' }}</div>
                                <div>{{ $counts['sections'][$course->id] ?? 0 }} سيكشن · {{ $counts['lessons'][$course->id] ?? 0 }} درس</div>
                                @can('courses.edit')
                                    <a href="{{ route('admin.courses.edit', $course) }}" class="underline block mt-2">تعديل</a>
                                @endcan
                            </div>
                        </details>
                    </div>
                @endforeach
            </div>
        </form>

        <div class="mt-4">{{ $courses->links() }}</div>
    @endif

    @include('admin.courses.partials.toast')

    @push('scripts')
        <script>
            /* شريط الإجراءات الجماعيّة: مخفيّ تمامًا حتى يختار المستخدم (2.15-ب) */
            const bulkBar = document.querySelector('[data-bulk-bar]');
            const bulkItems = () => [...document.querySelectorAll('[data-bulk-item]')];

            function syncBulk() {
                if (!bulkBar) return;
                const selected = bulkItems().filter((i) => i.checked).length;
                bulkBar.classList.toggle('hidden', selected === 0);
                bulkBar.classList.toggle('flex', selected > 0);
                const counter = bulkBar.querySelector('[data-bulk-count]');
                if (counter) counter.textContent = selected;
            }

            document.addEventListener('change', (e) => {
                if (e.target.matches('[data-bulk-all]')) {
                    bulkItems().forEach((i) => { i.checked = e.target.checked; });
                }
                if (e.target.matches('[data-bulk-item], [data-bulk-all]')) syncBulk();
            });

            syncBulk();
        </script>
    @endpush
@endsection

@section('mobile_action')
    @can('courses.create')
        <a href="{{ route('admin.courses.create') }}"
           class="btn block w-full text-center rounded-xl px-4 py-3 text-sm font-semibold"
           style="background: var(--color-brand-500); color: #04201c">+ تدريب جديد</a>
    @endcan
@endsection
