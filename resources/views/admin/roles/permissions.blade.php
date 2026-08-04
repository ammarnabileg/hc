@extends('layouts.admin')

@section('title', setting('admin.roles.permissions.msfwfa_alslahyat', 'مصفوفة الصلاحيّات'))

@section('content')
    <x-page-header :title="setting('admin.roles.permissions.msfwfa_alslahyat', 'مصفوفة الصلاحيّات')"
                   :subtitle="setting('admin.roles.permissions.kl_slahya_almwrd_alfal_bntaqatha_almsmwha', 'كلّ صلاحيّة «المورد.الفعل» بنطاقاتها المسموحة وشرطها')"
                   :breadcrumbs="[
                       ['label' => setting('admin.roles.permissions.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')],
                       ['label' => setting('admin.roles.permissions.aladwar_walslahyat', 'الأدوار والصلاحيّات'), 'url' => route('admin.roles.index')],
                       ['label' => setting('admin.roles.permissions.msfwfa_alslahyat', 'مصفوفة الصلاحيّات')],
                   ]" />

    <form method="get" class="card p-3 mb-4 flex flex-wrap items-end gap-3">
        <label class="flex flex-col gap-1 flex-1 min-w-[12rem]">
            <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.roles.permissions.bhth', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ setting('admin.roles.permissions.aktb_asm_alslahya_aw_mftahha', 'اكتب اسم الصلاحيّة أو مفتاحها…') }}"
                   class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="flex flex-col gap-1">
            <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.roles.permissions.almjmwaa', 'المجموعة') }}</span>
            <select name="group" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($groups as $key => $total)
                    <option value="{{ $key }}" @selected($group === $key)>{{ $key }} ({{ $total }})</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.roles.permissions.bhth', 'بحث') }}</button>
    </form>

    @if ($permissions->isEmpty())
        <x-empty :message="setting('admin.roles.permissions.mafysh_slahyat_mtabqa', 'مافيش صلاحيّات مطابقة')" />
    @else
        <div class="card p-0 overflow-hidden">
            <div class="min-w-0 overflow-x-auto no-scrollbar">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background: var(--surface-sunken)">
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.roles.permissions.alslahya', 'الصلاحيّة') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.roles.permissions.almftah', 'المفتاح') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.roles.permissions.alntaqat', 'النطاقات') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.roles.permissions.alshrt', 'الشرط') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permissions as $permission)
                            <tr class="border-t" style="border-color: var(--border)">
                                <td class="px-4 py-3">
                                    {{ $permission->label_ar }}
                                    @if ($permission->is_sensitive)<span title="{{ setting('admin.roles.permissions.hsasa', 'حسّاسة') }}">🔒</span>@endif
                                </td>
                                <td class="px-4 py-3 text-xs" style="color: var(--text-muted)">{{ $permission->key }}</td>
                                <td class="px-4 py-3 text-xs">{{ implode(' · ', $permission->allowed_scopes ?: []) }}</td>
                                {{-- النصّ العربيّ للقراءة، والمفتاح المقفول هو الذي **يُقيَّم** فعلًا (12.2.1-ج) --}}
                                <td class="px-4 py-3 text-xs" style="color: var(--text-muted)">
                                    {{ $permission->condition_key ?: setting('admin.roles.permissions.dayma', 'دائمًا') }}
                                    @if ($permission->condition_keys)
                                        <span class="block opacity-70" dir="ltr">{{ implode(' + ', $permission->condition_keys) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
