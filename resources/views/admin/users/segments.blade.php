@extends('layouts.admin')

@section('title', 'شرائح الجمهور')

@php
    /*
     | شرائح الجمهور (12.13): شريحة تُبنى مرّة وتُستهدَف من الإشعارات والمكافآت
     | والفعاليّات. والنوع فرقٌ حقيقيّ في السلوك: **الثابتة تُجمَّد الآن** فلا
     | يتغيّر عددها بتغيّر البيانات، و**الديناميكيّة تُعاد حسبتها**.
     |
     | فعلٌ رئيسيّ واحد للشاشة (`+ شريحة جديدة`) والباقي في «⋯» (2.15)، وما لا
     | يملكه المشاهد **يُخفى ولا يُعطَّل** (2.15-أ-7).
     */
    $canCreate = (bool) auth()->user()?->allows('user_segments.create');
@endphp

@section('content')
    <x-page-header title="شرائح الجمهور"
                   subtitle="ابنِ الشريحة مرّة، واستعملها في الإشعارات والمكافآت والفعاليّات"
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')],
                       ['label' => 'المستخدمون', 'url' => route('admin.users.index')],
                       ['label' => 'شرائح الجمهور'],
                   ]">
        @if ($canCreate)
            <x-slot:action>
                <button type="button" data-modal-open="segment-form"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">+ شريحة جديدة</button>
            </x-slot:action>
        @endif
    </x-page-header>

    {{-- بحث + فلاتر: النوع · الحالة · الاستخدام (12.13 · 2.15-أ-4) --}}
    <x-filters :action="route('admin.users.segments')">
        <label class="block flex-1 min-w-[12rem]">
            <span class="block text-sm mb-1">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="اسم الشريحة…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="block">
            <span class="block text-sm mb-1">النوع</span>
            <select name="type" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">الحالة</span>
            <select name="state" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">نشطة</option>
                <option value="archived" @selected($filters['state'] === 'archived')>مؤرشفة</option>
                <option value="all" @selected($filters['state'] === 'all')>الكلّ</option>
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">الاستخدام</span>
            <select name="used" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="used" @selected($filters['used'] === 'used')>مستخدَمة</option>
                <option value="unused" @selected($filters['used'] === 'unused')>غير مستخدَمة</option>
            </select>
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm" style="background: var(--surface-raised)">تصفية</button>
    </x-filters>

    {{-- ⭐ المعاينة اللحظيّة: العدد + عيّنة أعضاء (عددها إعداد) — 12.13 --}}
    @if ($previewed)
        <div class="card p-4 mb-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm">المطابقون الآن: <strong>{{ number_format((int) $previewCount) }}</strong></div>
                <x-state-badge :state="$previewCount > 0 ? 'ok' : 'warn'"
                               :label="$previewCount > 0 ? 'الشريحة فيها ناس' : 'مافيش حدّ مطابق'" />
            </div>

            <p class="text-xs mt-1" style="color: var(--text-muted)">{{ $service->summary($rule) }}</p>

            @if ($preview->isNotEmpty())
                <ul class="mt-2 text-xs space-y-1" style="color: var(--text-muted)">
                    @foreach ($preview as $member)
                        <li class="truncate">{{ $member->shortName() }} — #{{ $member->code }}</li>
                    @endforeach
                </ul>
            @endif

            <p class="text-xs mt-3" style="color: var(--text-muted)">
                {{ setting('admin.segments.preview_hint', 'المعاينة بتتحسب في حدود نطاقك أنت — مش في المنصّة كلّها.') }}
            </p>
        </div>
    @endif

    @if ($segments->isEmpty())
        <x-empty :message="setting('admin.segments.empty_message', 'ابنِ شريحتك الأولى')">
            @if ($canCreate)
                <button type="button" data-modal-open="segment-form"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">+ شريحة جديدة</button>
            @endif
        </x-empty>
    @else
        <div class="card p-2">
            <x-table label="شرائح الجمهور">
                <thead>
                    <tr class="text-right text-xs" style="color: var(--text-muted)">
                        <th class="p-2">الشريحة</th>
                        <th class="p-2">ملخّص المعايير</th>
                        <th class="p-2">الأعضاء</th>
                        <th class="p-2">النوع</th>
                        <th class="p-2">مستخدَمة في</th>
                        <th class="p-2">آخر تحديث</th>
                        <th class="p-2">الحالة</th>
                        <th class="p-2">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($segments as $segment)
                        @php $links = $usage[$segment->id] ?? collect(); @endphp
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="p-2">
                                <div class="font-semibold">{{ $segment->name }}</div>
                                @if ($segment->description)
                                    <div class="text-xs" style="color: var(--text-muted)">{{ $segment->description }}</div>
                                @endif
                            </td>
                            <td class="p-2 text-xs" style="color: var(--text-muted)">{{ $service->summary((array) $segment->rule) }}</td>
                            <td class="p-2">
                                <a href="{{ route('admin.users.segments.members', $segment) }}" class="underline">
                                    {{ number_format((int) ($counts[$segment->id] ?? 0)) }}
                                </a>
                            </td>
                            <td class="p-2 text-xs">{{ $types[$segment->segment_type] ?? $segment->segment_type }}</td>
                            <td class="p-2 text-xs">
                                @if ($links->isEmpty())
                                    <span style="color: var(--text-muted)">مش مستخدَمة</span>
                                @else
                                    مستخدَمة في {{ $links->count() }} مكان
                                    <span class="block">
                                        @foreach ($links as $link)
                                            <a href="{{ $link['url'] }}" class="underline">{{ $link['label'] }}</a>@if (! $loop->last) · @endif
                                        @endforeach
                                    </span>
                                @endif
                            </td>
                            <td class="p-2 text-xs" style="color: var(--text-muted)">{{ $segment->last_built_at?->diffForHumans() ?? '—' }}</td>
                            <td class="p-2">
                                <x-state-badge :state="$segment->archived_at ? 'idle' : 'ok'"
                                               :label="$segment->archived_at ? 'مؤرشفة' : 'نشطة'" />
                            </td>
                            <td class="p-2">
                                <div class="flex flex-wrap gap-2 text-xs">
                                    @can('user_segments.edit')
                                        <a href="{{ route('admin.users.segments', ['edit' => $segment->id]) }}" class="underline">تعديل</a>
                                    @endcan

                                    @can('user_segments.create')
                                        <form method="post" action="{{ route('admin.users.segments.duplicate', $segment) }}">
                                            @csrf
                                            <button class="underline">تكرار</button>
                                        </form>
                                    @endcan

                                    <a href="{{ route('admin.users.segments.members', $segment) }}" class="underline">معاينة الأعضاء</a>

                                    @can('user_segments.archive')
                                        <form method="post" action="{{ route('admin.users.segments.archive', $segment) }}">
                                            @csrf
                                            <button class="underline">{{ $segment->archived_at ? 'رجّعها للخدمة' : 'أرشفة' }}</button>
                                        </form>
                                    @endcan

                                    @can('user_segments.delete')
                                        <form method="post" action="{{ route('admin.users.segments.destroy', $segment) }}"
                                              onsubmit="return confirm('{{ $links->isEmpty() ? 'تمسح الشريحة دي؟' : str_replace(':count', $links->count(), setting('admin.segments.delete_warning', 'الشريحة دي مستخدَمة في :count مكان — أرشفها بدل ما تمسحها.')) }}')">
                                            @csrf
                                            @method('delete')
                                            <button class="underline" style="color: var(--color-state-danger)">حذف</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </div>

        <p class="mt-3 text-xs">
            <a href="{{ route('admin.users.segments.export') }}" class="underline">تصدير الشرائح (CSV)</a>
        </p>
    @endif

    {{-- ================================================ باني المعايير (12.13) --}}
    <x-modal id="segment-form" :title="$editing ? 'تعديل شريحة' : 'شريحة جديدة'">
        <form method="post" action="{{ route('admin.users.segments.preview') }}" class="space-y-3">
            @csrf
            @if ($editing)
                <input type="hidden" name="segment_id" value="{{ $editing->id }}">
            @endif

            <x-form.input name="name" label="اسم الشريحة" required :value="$editing->name ?? ''" />
            <x-form.input name="description" label="وصف مختصر" :value="$editing->description ?? ''" />

            {{-- ⭐ النوع: الفرق سلوكيّ لا تسمية (12.13) --}}
            <label class="block text-sm">
                النوع
                <select name="segment_type" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected(($editing->segment_type ?? 'dynamic') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="block text-xs mt-1" style="color: var(--text-muted)">
                    {{ setting('admin.segments.type_hint', 'الديناميكيّة بتتحدّث لوحدها مع تغيّر البيانات، والثابتة بتتجمّد على أعضائها دلوقتي.') }}
                </span>
            </label>

            {{-- الربط بين المجموعات: AND / OR (12.13) --}}
            <label class="block text-sm">
                الربط بين المجموعات
                <select name="match" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($matchModes as $key => $label)
                        <option value="{{ $key }}" @selected(($rule['match'] ?? 'all') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            @for ($g = 0; $g < $groupCount; $g++)
                @php
                    $group = $rule['groups'][$g] ?? ['match' => 'all', 'conditions' => []];
                    $picked = collect($group['conditions'] ?? [])->keyBy(fn ($condition) => $condition['field'] ?? '');
                @endphp

                <details class="card p-3" @if ($g === 0 || $picked->isNotEmpty()) open @endif>
                    <summary class="text-sm cursor-pointer">مجموعة {{ $g + 1 }}</summary>

                    <label class="block text-sm mt-2">
                        الربط داخل المجموعة
                        <select name="groups[{{ $g }}][match]" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($matchModes as $key => $label)
                                <option value="{{ $key }}" @selected(($group['match'] ?? 'all') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="grid md:grid-cols-2 gap-3 mt-3">
                        @foreach ($criteria as $field => $meta)
                            @php $current = $picked[$field] ?? null; @endphp

                            <div>
                                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ $meta['label'] }}</span>
                                <input type="hidden" name="groups[{{ $g }}][conditions][{{ $field }}][field]" value="{{ $field }}">

                                @if ($meta['type'] === 'ids' || $meta['type'] === 'keys')
                                    <select name="groups[{{ $g }}][conditions][{{ $field }}][values][]" multiple size="3"
                                            class="w-full rounded-xl px-3 py-2 text-sm"
                                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                        @foreach ($options[$field] ?? [] as $value => $label)
                                            <option value="{{ $value }}"
                                                    @selected(in_array((string) $value, array_map('strval', (array) ($current['values'] ?? [])), true))>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($meta['type'] === 'range')
                                    <div class="flex gap-2">
                                        <input type="number" name="groups[{{ $g }}][conditions][{{ $field }}][min]" placeholder="من"
                                               value="{{ $current['min'] ?? '' }}" class="w-full rounded-xl px-3 py-2 text-sm"
                                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                        <input type="number" name="groups[{{ $g }}][conditions][{{ $field }}][max]" placeholder="إلى"
                                               value="{{ $current['max'] ?? '' }}" class="w-full rounded-xl px-3 py-2 text-sm"
                                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    </div>
                                @else
                                    <input type="number" min="1" name="groups[{{ $g }}][conditions][{{ $field }}][value]"
                                           value="{{ $current['value'] ?? '' }}" class="w-full rounded-xl px-3 py-2 text-sm"
                                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </details>
            @endfor

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm motion-standard"
                        style="background: var(--surface-sunken)">معاينة لحظيّة</button>

                @if ($canCreate)
                    <button type="submit" formaction="{{ route('admin.users.segments.store') }}"
                            class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">حفظ الشريحة</button>
                @endif
            </div>
        </form>
    </x-modal>
@endsection
