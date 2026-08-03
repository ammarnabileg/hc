@extends('layouts.admin')

@section('title', 'تحرير دور: '.$role->name_ar)

@section('content')
    <x-page-header :title="$role->name_ar"
                   :subtitle="$role->description"
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')],
                       ['label' => 'الأدوار والصلاحيّات', 'url' => route('admin.roles.index')],
                       ['label' => $role->name_ar],
                   ]">
        <x-slot:action>
            @include('admin.roles.partials.audit-hover', ['log' => $lastChange])

            @if ($canDelete)
                <form method="post" action="{{ route('admin.roles.destroy', $role) }}"
                      onsubmit="return confirm('متأكّد إنك عايز تمسح الدور ده؟ الإجراء ده مالوش رجعة.')">
                    @csrf
                    @method('delete')
                    <button type="submit" class="rounded-xl px-3 py-2 text-sm motion-standard"
                            style="background: var(--surface-raised); color: var(--color-state-danger)">حذف</button>
                </form>
            @elseif (! $role->is_deletable)
                <span class="text-xs" style="color: var(--text-muted)">🔒 {{ setting('admin.roles.protected_message', 'دور محميّ') }}</span>
            @endif
        </x-slot:action>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-[16rem_1fr]">
        {{-- مجموعات العرض الثمانية — والمصفوفة تُحمَّل مجموعةً مجموعة (24.1) --}}
        <nav class="card p-2 h-max">
            <div class="text-xs px-2 py-1" style="color: var(--text-muted)">مجموعات الصلاحيّات</div>
            <div class="flex lg:block gap-2 min-w-0 overflow-x-auto no-scrollbar">
                @foreach ($groups as $key => $total)
                    <a href="{{ route('admin.roles.edit', ['role' => $role, 'group' => $key]) }}"
                       class="shrink-0 lg:block rounded-xl px-3 py-2 text-sm motion-standard"
                       style="{{ $group === $key ? 'background: var(--color-brand-500); color:#04201c; font-weight:700' : 'color: var(--text)' }}">
                        {{ $key }} <span class="opacity-70">({{ $total }})</span>
                    </a>
                @endforeach
            </div>
        </nav>

        <section class="card p-4 min-w-0">
            {{-- بحث في الصلاحيّات — شرط قبول الشاشة (12.2.1-ط) --}}
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <label class="flex flex-col gap-1 flex-1 min-w-[12rem]">
                    <span class="text-xs" style="color: var(--text-muted)">بحث في الصلاحيّات</span>
                    <input type="search" data-perm-search value="{{ $search }}" placeholder="اكتب اسم الصلاحيّة أو مفتاحها…"
                           class="rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                @if ($canEdit)
                    {{-- تفعيل المجموعة دفعة واحدة بضغطة — شرط قبول 12.2 --}}
                    <button type="button" data-perm-bulk="on" class="rounded-xl px-3 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--surface-raised); color: var(--text)">تفعيل المجموعة كلّها</button>

                    <button type="button" data-perm-bulk="off" class="rounded-xl px-3 py-2 text-sm motion-standard"
                            style="background: var(--surface-raised); color: var(--text-muted)">إلغاء الكلّ</button>
                @endif

                <span class="text-xs" style="color: var(--text-muted)">
                    ⭐ «manage» بتفرد <b>خمسة</b> أفعال (إنشاء · تعديل · حذف · أرشفة · إسناد) ظاهرةً عند الحفظ — تشوف بعينك كلّ سطر اتمنح.
                </span>
            </div>

            @if ($rows->isEmpty())
                <x-empty message="مافيش صلاحيّات في المجموعة دي" />
            @else
                <form method="post" action="{{ route('admin.roles.update', $role) }}">
                    @csrf
                    @method('put')
                    <input type="hidden" name="group" value="{{ $group }}">

                    <div class="min-w-0 overflow-x-auto no-scrollbar">
                        <table class="w-full text-sm">
                            <thead>
                                <tr style="background: var(--surface-sunken)">
                                    <th class="px-3 py-2"></th>
                                    <th class="text-start px-3 py-2 font-semibold">الصلاحيّة</th>
                                    <th class="text-start px-3 py-2 font-semibold">النطاق</th>
                                    <th class="text-start px-3 py-2 font-semibold">الأثر</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    @php $permission = $row['permission']; @endphp
                                    <tr class="border-t" style="border-color: var(--border)"
                                        data-perm-row="{{ $permission->key }} {{ $permission->label_ar }} {{ $permission->description }}">
                                        <td class="px-3 py-2">
                                            {{-- ما لا يملكه المستخدم **يُخفى** ولا يُعطَّل (2.15-أ-7) --}}
                                            @if ($canEdit)
                                                <input type="checkbox" name="rows[{{ $permission->id }}][on]" value="1"
                                                       data-perm-toggle
                                                       @checked($row['granted'])
                                                       aria-label="تفعيل {{ $permission->key }}">
                                            @else
                                                <x-state-badge :state="$row['granted'] ? 'ok' : 'idle'"
                                                               :label="$row['granted'] ? 'ممنوحة' : 'غير ممنوحة'" />
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="font-semibold">
                                                {{ $permission->label_ar }}
                                                @if ($permission->action === 'manage')
                                                    <span class="text-xs" style="color: var(--color-state-honor)">⭐ manage</span>
                                                @endif
                                                @if ($permission->is_sensitive)
                                                    <span title="صلاحيّة حسّاسة">🔒</span>
                                                @endif
                                            </div>
                                            <div class="text-xs" style="color: var(--text-muted)">{{ $permission->key }}</div>
                                        </td>
                                        <td class="px-3 py-2">
                                            {{-- اختيار النطاق لكلّ صلاحيّة من نطاقاتها المسموحة (12.2.1-ب) --}}
                                            @if ($canEdit)
                                                <select name="rows[{{ $permission->id }}][scope]"
                                                        class="rounded-xl px-2 py-1 text-xs"
                                                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                                                        aria-label="نطاق {{ $permission->key }}">
                                                    @foreach ($row['scopes'] as $scope)
                                                        <option value="{{ $scope }}" @selected($row['scope'] === $scope)>{{ $scope }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <span class="text-xs" style="color: var(--text-muted)">{{ $row['scope'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            {{-- Deny/Allow لكلّ سطر — والمنع يغلب الإذن دائمًا (12.2.1-ز-1) --}}
                                            @if ($canEdit)
                                                <select name="rows[{{ $permission->id }}][effect]"
                                                        class="rounded-xl px-2 py-1 text-xs"
                                                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                                                        aria-label="أثر {{ $permission->key }}">
                                                    <option value="allow" @selected($row['effect'] === 'allow')>إذن</option>
                                                    <option value="deny" @selected($row['effect'] === 'deny')>منع</option>
                                                </select>
                                            @else
                                                <span class="text-xs" style="color: var(--text-muted)">{{ $row['effect'] === 'deny' ? 'منع' : 'إذن' }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p data-perm-empty class="hidden text-sm mt-3" style="color: var(--text-muted)">مافيش صلاحيّة بالاسم ده.</p>

                    @if ($canEdit)
                        <button type="submit" class="mt-4 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">حفظ المجموعة</button>
                    @else
                        <p class="mt-4 text-xs" style="color: var(--text-muted)">قراءة فقط — مالكش صلاحيّة تعديل الأدوار.</p>
                    @endif
                </form>
            @endif
        </section>
    </div>
@endsection
