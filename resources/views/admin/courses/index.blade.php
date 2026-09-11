@extends('layouts.admin')

@section('title', setting('admin.courses.index.altdrybat', 'التدريبات'))

@section('content')
    {{-- التدريبات (12.4-ب · 24.1) --}}
    <x-page-header
        :title="setting('admin.courses.index.altdrybat', 'التدريبات')"
        :subtitle="setting('admin.courses.index.anshy_altdryb_wsarh_wathh_wabn_mhtwah', 'أنشئ التدريب وسعّره وأتِحه وابنِ محتواه.')"
        :breadcrumbs="[['label' => setting('admin.courses.index.idara_altdryb', 'إدارة التدريب'), 'url' => route('admin.paths.index')], ['label' => setting('admin.courses.index.altdrybat', 'التدريبات')]]">
        <x-slot:action>
            @can('courses.create')
                <a href="{{ route('admin.courses.create') }}"
                   class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.index.tdryb_jdyd_2', '+ تدريب جديد') }}</a>
            @endcan
        </x-slot:action>
    </x-page-header>

    @include('admin.courses.partials.nav', ['current' => 'courses'])

    {{-- ثلاثة فلاتر ظاهرة + بحث، والباقي مطويّ (2.15-أ-4) --}}
    <x-filters :action="route('admin.courses.index')">
        <label class="block flex-1 min-w-[12rem]">
            <span class="block text-sm mb-1">{{ setting('admin.courses.index.bhth', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ setting('admin.courses.index.asm_altdryb_aw_alshhada', 'اسم التدريب أو الشهادة…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.courses.index.almsar', 'المسار') }}</span>
            <select name="path" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.courses.index.alkl', 'الكلّ') }}</option>
                @foreach ($paths as $path)
                    <option value="{{ $path->id }}" @selected($filters['path'] === $path->id)>{{ $path->name_ar }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('admin.courses.index.alhala', 'الحالة') }}</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.courses.index.alkl', 'الكلّ') }}</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">{{ setting('admin.courses.index.tsfya', 'تصفية') }}</button>

        <x-slot:advanced>
            <label class="block">
                <span class="block text-sm mb-1">{{ setting('admin.courses.index.altsayr', 'التسعير') }}</span>
                <select name="pricing" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('admin.courses.index.alkl', 'الكلّ') }}</option>
                    <option value="free" @selected($filters['pricing'] === 'free')>{{ setting('admin.courses.index.mjany', 'مجّانيّ') }}</option>
                    <option value="paid" @selected($filters['pricing'] === 'paid')>{{ setting('admin.courses.index.mdfwa', 'مدفوع') }}</option>
                </select>
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($courses->isEmpty())
        {{-- تمييز «مفيش بيانات أصلًا» عن «الفلتر الحاليّ ما طابقش حاجة» — فلا تُعرَض
             رسالة «ابدأ بأوّل واحد» المضلّلة لمّا يكون السبب فلترًا نشطًا لا نقصًا فعليًّا. --}}
        <x-empty :message="setting('admin.courses.index.lsh_mfysh_tdrybat_abda_bawl_wahd', 'لسّه مفيش تدريبات — ابدأ بأوّل واحد.')"
                 :action="auth()->user()->can('courses.create') ? setting('admin.courses.index.tdryb_jdyd', 'تدريب جديد') : null"
                 :href="route('admin.courses.create')"
                 :filtered="$filters['q'] !== '' || $filters['path'] !== 0 || $filters['status'] !== '' || $filters['pricing'] !== ''" />
    @else
        <form method="post" action="{{ route('admin.courses.bulk') }}" data-bulk-form>
            @csrf

            {{-- ⭐ الإجراء الجماعيّ يظهر عند الاختيار فقط، ومخفيّ تمامًا قبله (2.15-ب) --}}
            @can('courses.edit')
                <div class="card p-3 mb-3 hidden items-center gap-3 flex-wrap" data-bulk-bar>
                    <span class="text-sm"><span data-bulk-count>0</span> {{ setting('admin.courses.index.mkhtar', 'مختار') }}</span>
                    <select name="action" class="rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="publish">{{ setting('admin.courses.index.nshr', 'نشر') }}</option>
                        <option value="hide">{{ setting('admin.courses.index.ikhfa', 'إخفاء') }}</option>
                        <option value="archive">{{ setting('admin.courses.index.arshfa', 'أرشفة') }}</option>
                        <option value="move_path">{{ setting('admin.courses.index.nql_lmsar', 'نقل لمسار') }}</option>
                        <option value="price">{{ setting('admin.courses.index.tsayr_dfaa', 'تسعير دفعة') }}</option>
                    </select>
                    <select name="path_id" class="rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="">{{ setting('admin.courses.index.almsar_2', '— المسار —') }}</option>
                        @foreach ($paths as $path)
                            <option value="{{ $path->id }}">{{ $path->name_ar }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="price_coins" min="0" placeholder="{{ setting('admin.courses.index.alsar_balkwynz', 'السعر بالكوينز') }}"
                           class="rounded-xl px-3 py-2 text-sm w-40"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.index.nfdh', 'نفّذ') }}</button>
                </div>
            @endcan

            {{-- ديسكتوب: جدول بأعمدته الافتراضيّة (5–7) --}}
            <div class="hidden md:block card overflow-hidden">
                <table class="w-full text-sm"
                   {{-- حدّ الأعمدة الافتراضيّ من الإعدادات، و«وضع متقدّم» يرفعه (2.15-أ-5) --}}
                   @unless (advanced_mode()) data-columns-cap="{{ view_mode()->defaultColumns() }}" @endunless>
                    <thead style="background: var(--surface-sunken)">
                        <tr>
                            <th class="p-3 w-8">
                                <label class="inline-flex items-center" aria-label="{{ setting('courses.bulk.pick_all', 'اختيار الكلّ') }}">
                                    <input type="checkbox" data-bulk-all>
                                </label>
                            </th>
                            {{-- عمود الغلاف — التدريبات كانت بلا صورةٍ ظاهرة رغم وجود cover_path (12.4-ب) --}}
                            <th class="p-3 text-start">{{ setting('admin.courses.index.alghlaf', 'الغلاف') }}</th>
                            <th class="p-3 text-start">{{ setting('admin.courses.index.asm_alard', 'اسم العرض') }}</th>
                            <th class="p-3 text-start">{{ setting('admin.courses.index.asm_alshhada', 'اسم الشهادة') }}</th>
                            <th class="p-3 text-start">{{ setting('admin.courses.index.almsar_at', 'المسار(ات)') }}</th>
                            <th class="p-3 text-start">{{ setting('admin.courses.index.sykshnz_drws', 'سيكشنز/دروس') }}</th>
                            <th class="p-3 text-start">{{ setting('admin.courses.index.alsar', 'السعر') }}</th>
                            <th class="p-3 text-start">{{ setting('admin.courses.index.almsjlwn', 'المسجّلون') }}</th>
                            <th class="p-3 text-start">{{ setting('admin.courses.index.alhala', 'الحالة') }}</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($courses as $course)
                            <tr style="border-top: 1px solid var(--border)">
                                <td class="p-3">
                                    {{-- التسمية هي هدف اللمس لا الصندوق: النايتف 13px ولا يُكبَّر بلا تشويه (2.15-ج) --}}
                                    <label class="inline-flex items-center" aria-label="{{ setting('courses.bulk.pick_one', 'اختيار هذا التدريب') }}">
                                        <input type="checkbox" name="ids[]" value="{{ $course->id }}" data-bulk-item>
                                    </label>
                                </td>
                                <td class="p-3">
                                    @if ($course->cover_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($course->cover_path) }}" alt=""
                                             class="w-10 h-10 rounded-lg object-cover" loading="lazy">
                                    @else
                                        <span style="color: var(--text-muted)">—</span>
                                    @endif
                                </td>
                                <td class="p-3 font-semibold">{{ $course->name_ar }}</td>
                                <td class="p-3">{{ $course->cert_name_ar ?: '—' }}</td>
                                <td class="p-3">{{ implode('، ', $pathNames[$course->id] ?? []) ?: '—' }}</td>
                                <td class="p-3">{{ $counts['sections'][$course->id] ?? 0 }} / {{ $counts['lessons'][$course->id] ?? 0 }}</td>
                                <td class="p-3">{{ $course->is_free ? setting('admin.courses.index.mjany', 'مجّانيّ') : (int) $course->price_coins }}</td>
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
                                        <summary class="cursor-pointer list-none px-2" aria-label="{{ setting('admin.courses.index.ijraat', 'إجراءات') }}">⋯</summary>
                                        <div class="card absolute end-0 mt-1 p-2 w-52 z-20 text-start space-y-1">
                                            @can('courses.edit')
                                                <a href="{{ route('admin.courses.edit', $course) }}" class="block px-2 py-1 text-sm">{{ setting('admin.courses.index.tadyl', 'تعديل') }}</a>
                                            @endcan
                                            <a href="{{ route('admin.courses.stats', $course) }}" class="block px-2 py-1 text-sm">{{ setting('admin.courses.index.ihsayyat', 'إحصائيّات') }}</a>
                                            <a href="{{ route('admin.courses.preview', $course) }}" class="block px-2 py-1 text-sm">{{ setting('admin.courses.index.maayna_ktalb', 'معاينة كطالب') }}</a>
                                            {{-- ⭐ معاينة الامتحان النهائيّ كما سيُبنى (12.4-هـ) — غير «معاينة كطالب» --}}
                                            <a href="{{ route('admin.courses.exam-preview', $course) }}" class="block px-2 py-1 text-sm">{{ setting('admin.courses.index.maayna_alamthan_alnhayy', 'معاينة الامتحان النهائيّ') }}</a>
                                            <a href="{{ route('admin.courses.audit', $course) }}" class="block px-2 py-1 text-sm">{{ setting('admin.courses.index.sjl_altdqyq', 'سجلّ التدقيق') }}</a>

                                            {{-- «تكرار/نسخ (Duplicate) لتدريب» (12.4-هـ) — بصلاحيّة الإنشاء
                                                 لأنّها تُنشئ تدريبًا جديدًا فعلًا، ومَن لا يملكها لا يرى العنصر (2.15-أ-7) --}}
                                            @can('courses.create')
                                                <form method="post" action="{{ route('admin.courses.duplicate', $course) }}"
                                                      onsubmit="return confirm('{{ setting('courses.duplicate.confirm_text') }}')">
                                                    @csrf
                                                    <button type="submit" class="block w-full text-start px-2 py-1 text-sm">
                                                        {{ setting('courses.duplicate.action_label') }}
                                                    </button>
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

            {{-- موبايل: كروت رأسيّة بأهمّ 3 حقول والباقي بالتوسيع (2.15-ج) --}}
            <div class="md:hidden space-y-3">
                @foreach ($courses as $course)
                    <div class="card p-4">
                        <div class="flex items-start gap-2">
                            <label class="inline-flex items-center" aria-label="{{ setting('courses.bulk.pick_one', 'اختيار هذا التدريب') }}">
                                <input type="checkbox" name="ids[]" value="{{ $course->id }}" data-bulk-item>
                            </label>
                            @if ($course->cover_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($course->cover_path) }}" alt=""
                                     class="w-10 h-10 rounded-lg object-cover shrink-0" loading="lazy">
                            @else
                                <span style="color: var(--text-muted)">—</span>
                            @endif
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold truncate">{{ $course->name_ar }}</div>
                                <div class="text-xs mt-1" style="color: var(--text-muted)">
                                    {{ $course->is_free ? setting('admin.courses.index.mjany', 'مجّانيّ') : (int) $course->price_coins.setting('admin.courses.index.kwynz', ' كوينز') }} ·
                                    <a href="{{ route('admin.courses.enrollees', $course) }}" class="underline">
                                        {{ $counts['enrollments'][$course->id] ?? 0 }} {{ setting('admin.courses.index.msjl', 'مسجّل') }}
                                    </a>
                                </div>
                            </div>
                            <x-state-badge :state="$course->status === 'published' ? 'ok' : 'warn'"
                                           :label="$statuses[$course->status] ?? $course->status" />
                        </div>
                        <details class="mt-3">
                            <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">{{ setting('admin.courses.index.tfasyl_aktr', 'تفاصيل أكتر') }}</summary>
                            <div class="mt-2 text-sm space-y-1">
                                <div>{{ setting('admin.courses.index.asm_alshhada_2', 'اسم الشهادة:') }} {{ $course->cert_name_ar ?: '—' }}</div>
                                <div>{{ setting('admin.courses.index.almsarat', 'المسارات:') }} {{ implode('، ', $pathNames[$course->id] ?? []) ?: '—' }}</div>
                                <div>{{ $counts['sections'][$course->id] ?? 0 }} {!! strtr(setting('admin.courses.index.sykshn_v1_drs', 'سيكشن · :v1 درس'), [':v1' => e($counts['lessons'][$course->id] ?? 0)]) !!}</div>
                                @can('courses.edit')
                                    <a href="{{ route('admin.courses.edit', $course) }}" class="underline block mt-2">{{ setting('admin.courses.index.tadyl', 'تعديل') }}</a>
                                @endcan

                                {{-- ونفس الإجراء على الموبايل: لا ميزة تسقط بالمقاس (2.15-ج) --}}
                                @can('courses.create')
                                    <form method="post" action="{{ route('admin.courses.duplicate', $course) }}" class="mt-2"
                                          onsubmit="return confirm('{{ setting('courses.duplicate.confirm_text') }}')">
                                        @csrf
                                        <button type="submit" class="underline text-sm">
                                            {{ setting('courses.duplicate.action_label') }}
                                        </button>
                                    </form>
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
           style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.courses.index.tdryb_jdyd_2', '+ تدريب جديد') }}</a>
    @endcan
@endsection
