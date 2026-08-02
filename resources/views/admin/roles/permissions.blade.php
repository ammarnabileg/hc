@extends('layouts.admin')

@section('title', 'مصفوفة الصلاحيّات')

@section('content')
    <x-page-header title="مصفوفة الصلاحيّات"
                   subtitle="كلّ صلاحيّة «المورد.الفعل» بنطاقاتها المسموحة وشرطها"
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')],
                       ['label' => 'الأدوار والصلاحيّات', 'url' => route('admin.roles.index')],
                       ['label' => 'مصفوفة الصلاحيّات'],
                   ]" />

    <form method="get" class="card p-3 mb-4 flex flex-wrap items-end gap-3">
        <label class="flex flex-col gap-1 flex-1 min-w-[12rem]">
            <span class="text-xs" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="اكتب اسم الصلاحيّة أو مفتاحها…"
                   class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="flex flex-col gap-1">
            <span class="text-xs" style="color: var(--text-muted)">المجموعة</span>
            <select name="group" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($groups as $key => $total)
                    <option value="{{ $key }}" @selected($group === $key)>{{ $key }} ({{ $total }})</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">بحث</button>
    </form>

    @if ($permissions->isEmpty())
        <x-empty message="مافيش صلاحيّات مطابقة" />
    @else
        <div class="card p-0 overflow-hidden">
            <div class="overflow-x-auto no-scrollbar">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background: var(--surface-sunken)">
                            <th class="text-start px-4 py-3 font-semibold">الصلاحيّة</th>
                            <th class="text-start px-4 py-3 font-semibold">المفتاح</th>
                            <th class="text-start px-4 py-3 font-semibold">النطاقات</th>
                            <th class="text-start px-4 py-3 font-semibold">الشرط</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permissions as $permission)
                            <tr class="border-t" style="border-color: var(--border)">
                                <td class="px-4 py-3">
                                    {{ $permission->label_ar }}
                                    @if ($permission->is_sensitive)<span title="حسّاسة">🔒</span>@endif
                                </td>
                                <td class="px-4 py-3 text-xs" style="color: var(--text-muted)">{{ $permission->key }}</td>
                                <td class="px-4 py-3 text-xs">{{ implode(' · ', $permission->allowed_scopes ?: []) }}</td>
                                <td class="px-4 py-3 text-xs" style="color: var(--text-muted)">{{ $permission->condition_key ?? 'دائمًا' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
