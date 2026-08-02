@extends('layouts.app')

@section('title', 'التعليمات')

@section('content')
    {{-- التعليمات: قناة بثّ إداريّة اتّجاه واحد (12.6-أ · 24.3) --}}
    <x-page-header
        title="التعليمات"
        subtitle="ابعت منشورًا لجمهور محدَّد، وشوف مين قرأ ومين أقرّ."
        :breadcrumbs="[['label' => 'التوجيه والدعم', 'url' => route('admin.guidance.index')], ['label' => 'التعليمات']]">
        <x-slot:action>
            @can('announcements.create')
                <button type="button" data-modal-open="announcement-form"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">+ منشور</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    <x-tabs :tabs="$tabs" current="announcements" />

    <x-filters :action="route('admin.guidance.index')">
        <label class="block flex-1 min-w-[12rem]">
            <span class="block text-sm mb-1">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="عنوان المنشور…"
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
        <label class="flex items-center gap-2 text-sm mt-6">
            <input type="checkbox" name="pinned" value="1" @checked($filters['pinned'])> المثبَّت فقط
        </label>
        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">تصفية</button>
    </x-filters>

    @if ($announcements->isEmpty())
        <x-empty message="لا منشورات — ابدأ أوّل بثّ." />
    @else
        <div class="space-y-3">
            @foreach ($announcements as $announcement)
                @php $stat = $stats[$announcement->id] ?? ['reads' => 0, 'acks' => 0, 'rate' => 0]; @endphp
                <div class="card p-4">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <div class="font-semibold flex items-center gap-2">
                                {{ $announcement->title }}
                                @if ($announcement->is_pinned)<span title="مثبَّت" aria-label="مثبَّت"><x-icon name="placement" size="16" /></span>@endif
                            </div>
                            <div class="text-xs mt-1" style="color: var(--text-muted)">
                                الجمهور: {{ ['all' => 'الكلّ', 'role' => 'دور', 'course' => 'تدريب', 'path' => 'مسار', 'user' => 'أشخاص'][$announcement->audience['type'] ?? 'all'] ?? 'الكلّ' }}
                                @if ($announcement->requires_acknowledge)
                                    · إقرار بـ{{ $announcement->acknowledge_xp }} XP (مرّة واحدة)
                                @endif
                                · التفاعل {{ $announcement->reactions_enabled ? 'مسموح' : 'ممنوع' }}
                            </div>
                        </div>
                        <x-state-badge
                            :state="$announcement->status === 'published' ? 'ok' : ($announcement->status === 'archived' ? 'idle' : 'warn')"
                            :label="$statuses[$announcement->status] ?? $announcement->status" />
                    </div>

                    {{-- نسبة القراءة كشريط (24.3) --}}
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-xs">
                            <span>نسبة القراءة {{ $stat['rate'] }}%</span>
                            <span style="color: var(--text-muted)">{{ $stat['reads'] }} قراءة · {{ $stat['acks'] }} إقرار</span>
                        </div>
                        <div class="h-1 rounded-full overflow-hidden mt-1" style="background: var(--surface-sunken)">
                            <div class="h-full" style="width: {{ $stat['rate'] }}%; background: var(--color-brand-500)"></div>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-3 text-xs flex-wrap">
                        <a href="{{ route('admin.guidance.analytics', $announcement) }}" class="underline">تحليلات</a>

                        @can('announcements.edit')
                            <form method="post" action="{{ route('admin.guidance.announcements.duplicate', $announcement) }}">
                                @csrf
                                <button class="underline">نسخة جديدة</button>
                            </form>
                        @endcan

                        @can('announcements.archive')
                            @if ($announcement->status !== 'archived')
                                <form method="post" action="{{ route('admin.guidance.announcements.archive', $announcement) }}">
                                    @csrf
                                    <button class="underline">أرشفة</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $announcements->links() }}</div>
    @endif

    @can('announcements.create')
        <x-modal id="announcement-form" title="منشور جديد">
            <form method="post" action="{{ route('admin.guidance.announcements.store') }}" class="space-y-3">
                @csrf

                <x-form.input name="title" label="العنوان" required />

                <label class="block">
                    <span class="block text-sm mb-1">النصّ</span>
                    <textarea name="body" rows="4" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <div class="grid md:grid-cols-2 gap-3">
                    <x-form.input name="cta_label" label="نصّ زرّ CTA" />
                    <x-form.input name="cta_url" label="رابط الزرّ (Deep link)" />
                </div>

                {{-- استهداف بشرائح (12.6-أ) --}}
                <fieldset class="card p-3">
                    <legend class="text-sm px-1">الجمهور</legend>
                    <label class="block text-sm">
                        الشريحة
                        <select name="audience_type" data-audience class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="all">الكلّ</option>
                            <option value="role">حسب الدور</option>
                            <option value="course">حسب التدريب</option>
                            <option value="path">حسب المسار</option>
                            <option value="user">أشخاص بعينهم</option>
                        </select>
                    </label>

                    <div class="mt-2 hidden" data-audience-panel="role">
                        <select name="audience_keys[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($audiences['roles'] as $role)
                                <option value="{{ $role->key }}">{{ $role->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-2 hidden" data-audience-panel="course">
                        <select name="audience_ids[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($audiences['courses'] as $course)
                                <option value="{{ $course->id }}">{{ $course->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-2 hidden" data-audience-panel="path">
                        <select name="audience_ids[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($audiences['paths'] as $path)
                                <option value="{{ $path->id }}">{{ $path->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>
                </fieldset>

                {{-- السلوك: التفاعل والإقرار بـXP بسقف مرّة لكلّ منشور (12.6-أ) --}}
                <fieldset class="card p-3 space-y-2 text-sm">
                    <legend class="text-sm px-1">السلوك</legend>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="reactions_enabled" value="1"> اسمح بالتفاعل (إيموجي)
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="requires_acknowledge" value="1"> إلزام إقرار «قرأتُ وفهمت»
                    </label>
                    <div class="grid md:grid-cols-2 gap-3">
                        <x-form.input name="acknowledge_xp" label="XP الإقرار (مرّة واحدة لكلّ منشور)" type="number"
                                      :value="setting('announcements.acknowledge.default_xp', 0)" />
                        <x-form.input name="acknowledge_tickets" label="تذاكر الإقرار" type="number"
                                      :value="setting('announcements.acknowledge.default_tickets', 0)" />
                    </div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="push_to_notifications" value="1"> اعرضه كإشعار/Toast
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_pinned" value="1"> ثبّته أعلى القناة
                    </label>
                </fieldset>

                <div class="grid md:grid-cols-3 gap-3">
                    <label class="block">
                        <span class="block text-sm mb-1">الحالة</span>
                        <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-form.input name="scheduled_at" label="ينشر في" type="datetime-local" />
                    <x-form.input name="expires_at" label="يُؤرشَف في" type="datetime-local" />
                </div>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">حفظ المنشور</button>
            </form>
        </x-modal>
    @endcan

    @include('admin.courses.partials.toast')

    @push('scripts')
        <script>
            /* شرائح الجمهور: نعرض حقل الشريحة المختارة فقط (2.15 — إخفاء التعقيد) */
            const audience = document.querySelector('[data-audience]');
            audience?.addEventListener('change', () => {
                document.querySelectorAll('[data-audience-panel]').forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.audiencePanel !== audience.value);
                });
            });
        </script>
    @endpush
@endsection

@section('mobile_action')
    @can('announcements.create')
        <button type="button" data-modal-open="announcement-form"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">+ منشور</button>
    @endcan
@endsection
