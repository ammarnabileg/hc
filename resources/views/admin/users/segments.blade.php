@extends('layouts.admin')

@section('title', 'شرائح الجمهور')

@section('content')
    <x-page-header title="شرائح الجمهور"
                   subtitle="ابنِ الشريحة مرّة، واستعملها في الإشعارات والمكافآت والفعاليّات"
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')],
                       ['label' => 'المستخدمون', 'url' => route('admin.users.index')],
                       ['label' => 'شرائح الجمهور'],
                   ]" />

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- باني المعايير بمعاينة لحظيّة (24.1) --}}
        <section class="card p-4">
            <h3 class="font-bold text-sm mb-3">شريحة جديدة</h3>

            <form method="get" class="flex flex-wrap items-end gap-3">
                <label class="flex flex-col gap-1">
                    <span class="text-xs" style="color: var(--text-muted)">{{ $criteria['status'] }}</span>
                    <select name="status" class="rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="">الكلّ</option>
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(($rule['status'] ?? null) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-xs" style="color: var(--text-muted)">{{ $criteria['role'] }}</span>
                    <select name="role" class="rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="">الكلّ</option>
                        @foreach ($roles as $key => $label)
                            <option value="{{ $key }}" @selected(($rule['role'] ?? null) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-xs" style="color: var(--text-muted)">{{ $criteria['min_xp'] }}</span>
                    <input type="number" name="min_xp" min="0" value="{{ $rule['min_xp'] ?? '' }}"
                           class="rounded-xl px-3 py-2 text-sm w-28"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-xs" style="color: var(--text-muted)">{{ $criteria['registered_days'] }}</span>
                    <input type="number" name="registered_days" min="1" value="{{ $rule['registered_days'] ?? '' }}"
                           class="rounded-xl px-3 py-2 text-sm w-28"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <button type="submit" class="rounded-xl px-4 py-2 text-sm motion-standard"
                        style="background: var(--surface-sunken)">معاينة</button>
            </form>

            @if ($previewCount !== null)
                <div class="mt-4 rounded-xl p-3" style="background: var(--surface-sunken)">
                    <div class="text-sm">المطابقون: <strong>{{ number_format($previewCount) }}</strong></div>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">{{ $service->summary($rule) }}</div>

                    @if ($preview->isNotEmpty())
                        <ul class="mt-2 text-xs space-y-1" style="color: var(--text-muted)">
                            @foreach ($preview as $member)
                                <li class="truncate">{{ $member->shortName() }} — #{{ $member->code }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if (auth()->user()->allows('user_segments.create'))
                    <form method="post" action="{{ route('admin.users.segments.store') }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        @foreach ($rule as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach

                        <label class="flex flex-col gap-1 flex-1 min-w-[12rem]">
                            <span class="text-xs" style="color: var(--text-muted)">اسم الشريحة</span>
                            <input type="text" name="name" required maxlength="120" placeholder="مثلًا: المستنّيون اعتماد"
                                   class="rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </label>

                        <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">حفظ الشريحة</button>
                    </form>
                @endif
            @endif
        </section>

        {{-- الشرائح المحفوظة — تُعاد الاستفادة منها بلا إعادة بناء --}}
        <section class="card p-4">
            <h3 class="font-bold text-sm mb-3">الشرائح المحفوظة</h3>

            @if ($segments->isEmpty())
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.segments.empty_message', 'ابنِ شريحتك الأولى') }}</p>
            @else
                <ul class="divide-y" style="border-color: var(--border)">
                    @foreach ($segments as $segment)
                        <li class="flex flex-wrap items-center gap-2 py-3" style="border-color: var(--border)">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold truncate">{{ $segment->name }}</div>
                                <div class="text-xs truncate" style="color: var(--text-muted)">{{ $service->summary($segment->rule ?? []) }}</div>
                            </div>

                            <span class="text-xs" style="color: var(--text-muted)">{{ number_format((int) $segment->size) }} عضو</span>

                            <a href="{{ route('admin.users.segments', $segment->rule ?? []) }}"
                               class="text-xs hover:underline" style="color: var(--color-brand-500)">إعادة استخدام</a>

                            @if (auth()->user()->allows('user_segments.delete'))
                                <form method="post" action="{{ route('admin.users.segments.destroy', $segment) }}">
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="text-xs hover:underline" style="color: var(--color-state-danger)">حذف</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
