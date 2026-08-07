@extends('layouts.admin')

@section('title', setting('admin.roles.permission_assign.anwan', 'منح صلاحيّة فرديّة'))

@section('content')
    <x-page-header :title="setting('admin.roles.permission_assign.anwan', 'منح صلاحيّة فرديّة')"
                   :subtitle="setting('admin.roles.permission_assign.wsf', 'الاستثناء الفرديّ يجلس فوق الأدوار — والمنع يغلب الإذن دائمًا.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.roles.assign.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')],
                       ['label' => setting('admin.roles.assign.aladwar_walslahyat', 'الأدوار والصلاحيّات'), 'url' => route('admin.roles.index')],
                       ['label' => setting('admin.roles.permission_assign.anwan', 'منح صلاحيّة فرديّة')],
                   ]" />

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="card p-4">
            <h3 class="font-bold text-sm mb-3">{{ setting('admin.roles.permission_assign.mnh_jdyd', 'منح جديد') }}</h3>

            {{-- اختيار المستخدم أوّلًا ليُقرأ منه استثناءاته القائمة --}}
            <form method="get" class="flex flex-wrap items-end gap-3 mb-4">
                <label class="flex flex-col gap-1 flex-1 min-w-[12rem]">
                    <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.roles.assign.rqm_almstkhdm', 'رقم المستخدم') }}</span>
                    <input type="number" name="user" value="{{ $target?->id }}" min="1" required
                           class="rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
                <button type="submit" class="rounded-xl px-4 py-2 text-sm motion-standard"
                        style="background: var(--surface-sunken)">{{ setting('admin.roles.permission_assign.aard_astthnaath', 'اعرض استثناءاته') }}</button>
            </form>

            @if (! $target)
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.roles.assign.akhtr_almstkhdm_alawl_ashan_nard_adwyath', 'اختر المستخدم الأوّل عشان نعرض عضويّاته.') }}</p>
            @else
                <div class="rounded-xl p-3 mb-3 text-sm" style="background: var(--surface-sunken)">
                    {{ $target->name }} — #{{ $target->code }}
                </div>

                <form method="post" action="{{ route('admin.permissions.update', $target) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="user" value="{{ $target->id }}">

                    <label class="block text-sm">
                        <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.roles.permission_assign.alslahya', 'الصلاحيّة') }}</span>
                        <select name="permission" required class="mt-1 w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($permissions as $group => $rows)
                                <optgroup label="{{ $group }}">
                                    @foreach ($rows as $permission)
                                        <option value="{{ $permission->key }}">
                                            {{ $permission->label_ar }} ({{ $permission->key }})@if ($permission->is_sensitive) 🔒 @endif
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.roles.permission_assign.alntaq', 'النطاق') }}</span>
                        <select name="scope" required class="mt-1 w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($scopes as $scope)
                                <option value="{{ $scope }}">{{ $scope }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.roles.permission_assign.alathr', 'الأثر') }}</span>
                        <select name="effect" required class="mt-1 w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="allow">{{ setting('admin.roles.permission_assign.smah', 'سماح') }}</option>
                            <option value="deny">{{ setting('admin.roles.permission_assign.mnaa', 'منع') }}</option>
                        </select>
                    </label>

                    <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.roles.permission_assign.amnh', 'امنح') }}</button>
                </form>
            @endif
        </section>

        <section class="card p-4">
            <h3 class="font-bold text-sm mb-3">{{ setting('admin.roles.permission_assign.alastthnaat_alqaima', 'الاستثناءات القائمة') }}</h3>

            @if (! $target)
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.roles.assign.akhtr_almstkhdm_alawl_ashan_nard_adwyath', 'اختر المستخدم الأوّل عشان نعرض عضويّاته.') }}</p>
            @elseif ($overrides->isEmpty())
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.roles.permission_assign.mafysh_astthnaat_lsh', 'مافيش استثناءات فرديّة لسّه.') }}</p>
            @else
                <ul class="divide-y" style="border-color: var(--border)">
                    @foreach ($overrides as $override)
                        <li class="flex flex-wrap items-center gap-2 py-3" style="border-color: var(--border)">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold truncate">
                                    {{ $override->permission?->label_ar ?? $override->permission_id }}
                                    @if ($override->permission?->is_sensitive) 🔒 @endif
                                </div>
                                <div class="text-xs truncate" style="color: var(--text-muted)">
                                    {{ $override->scope }} ·
                                    <span style="color: {{ $override->effect === 'deny' ? 'var(--color-state-danger)' : 'var(--color-state-ok)' }}">
                                        {{ $override->effect === 'deny' ? setting('admin.roles.permission_assign.mnaa', 'منع') : setting('admin.roles.permission_assign.smah', 'سماح') }}
                                    </span>
                                    {{-- ⭐ `assigned_by` عمود FK واسم علاقة معًا — الوصول المباشر يُرجِع الرقم الخام لا العلاقة (getRelation() وحدها تتجاوز الظلّ) --}}
                                    @if ($assignedBy = $override->getRelation('assigned_by'))
                                        · {{ $assignedBy->shortName() }}
                                    @endif
                                </div>
                            </div>

                            <form method="post" action="{{ route('admin.permissions.destroy', $override) }}">
                                @csrf
                                @method('delete')
                                <button type="submit" class="text-xs hover:underline" style="color: var(--color-state-danger)">{{ setting('admin.roles.assign.shb', 'سحب') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
