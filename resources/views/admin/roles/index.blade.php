@extends('layouts.admin')

@section('title', setting('admin.roles.index.aladwar_walslahyat', 'الأدوار والصلاحيّات'))

@section('content')
    <x-page-header :title="setting('admin.roles.index.aladwar_walslahyat', 'الأدوار والصلاحيّات')"
                   :subtitle="setting('admin.roles.index.aldwr_tjmyaa_qdrat_almwrd_alfal_bntaqh', 'الدور تجميعة قدرات — «المورد.الفعل» بنطاقه وشرطه')"
                   :breadcrumbs="[
                       ['label' => setting('admin.roles.index.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')],
                       ['label' => setting('admin.roles.index.almstkhdmwn', 'المستخدمون'), 'url' => route('admin.users.index')],
                       ['label' => setting('admin.roles.index.aladwar_walslahyat', 'الأدوار والصلاحيّات')],
                   ]">
        <x-slot:action>
            @can('roles.create')
                <button type="button" data-modal-open="new-role-modal"
                        class="btn hidden md:inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.roles.index.dwr_jdyd', '+ دور جديد') }}</button>
            @endcan

            <details class="relative">
                <summary class="list-none cursor-pointer rounded-xl px-3 py-2 text-sm select-none"
                         style="background: var(--surface-raised)" aria-label="{{ setting('admin.roles.index.afaal_akhra', 'أفعال أخرى') }}">⋯</summary>
                <div class="card absolute end-0 mt-2 w-56 p-1 z-40">
                    @can('roles.assign')
                        <a href="{{ route('admin.roles.assign') }}" class="block rounded-lg px-3 py-2 text-sm motion-standard">{{ setting('admin.roles.index.isnad_dwr_dakhl_adwya', 'إسناد دور داخل عضويّة') }}</a>
                    @endcan
                    @can('permissions.list')
                        <a href="{{ route('admin.permissions.index') }}" class="block rounded-lg px-3 py-2 text-sm motion-standard">{{ setting('admin.roles.index.tsfh_msfwfa_alslahyat', 'تصفّح مصفوفة الصلاحيّات') }}</a>
                    @endcan
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    <div class="card p-3 mb-4 text-xs" style="color: var(--text-muted)">
        {{ setting('admin.roles.deny_message', 'المنع يغلب الإذن.') }}
        @unless (auth()->user()->isPlatformOwner())
            — {{ setting('admin.roles.owner_only_note', 'المجموعة المحميّة لمالك المنصّة وحده.') }}
        @endunless
    </div>

    @if ($roles->isEmpty())
        <x-empty :message="setting('admin.roles.empty_message', 'مفيش دور مخصّص لسّه — ابدأ بنسخ قالب')" />
    @else
        <div class="card p-0 overflow-hidden hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.roles.index.aldwr', 'الدور') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.roles.index.alnwa', 'النوع') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.roles.index.alslahyat', 'الصلاحيّات') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.roles.index.almstkhdmwn', 'المستخدمون') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.roles.index.akhr_tadyl', 'آخر تعديل') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr class="border-t" style="border-color: var(--border)">
                            <td class="px-4 py-3">
                                <span class="font-semibold">{{ $role->name_ar }}</span>
                                @unless ($editor->canDelete($role))
                                    <span class="ms-1" title="{{ setting('admin.roles.protected_message', 'دور محميّ') }}">🔒</span>
                                @endunless
                                <div class="text-xs" style="color: var(--text-muted)">{{ $role->key }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-muted)">
                                {{ $role->is_system ? setting('admin.roles.index.qalb', 'قالب') : setting('admin.roles.index.mkhss', 'مخصّص') }} ·
                                {{ match ($role->layer) { 'platform' => setting('admin.roles.index.mnsa', 'منصّة'), 'volunteer' => setting('admin.roles.index.ttwa', 'تطوّع'), default => setting('admin.roles.index.mstkhdm', 'مستخدم') } }}
                            </td>
                            <td class="px-4 py-3">{{ number_format($role->permissions_count) }}</td>
                            <td class="px-4 py-3">{{ number_format($role->users_count) }}</td>
                            <td class="px-4 py-3">
                                @include('admin.roles.partials.audit-hover', ['log' => $lastChanges[$role->id] ?? null])
                            </td>
                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('admin.roles.edit', $role) }}" class="text-xs hover:underline"
                                   style="color: var(--color-brand-500)">{{ setting('admin.roles.index.thryr', 'تحرير') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="md:hidden space-y-3">
            @foreach ($roles as $role)
                <a href="{{ route('admin.roles.edit', $role) }}" class="card p-3 block">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold">{{ $role->name_ar }}</span>
                        @unless ($editor->canDelete($role))
                            <span aria-label="{{ setting('admin.roles.index.dwr_mhmy', 'دور محميّ') }}">🔒</span>
                        @endunless
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ number_format($role->permissions_count) }} {!! strtr(setting('admin.roles.index.slahya_v1_mstkhdm', 'صلاحيّة · :v1 مستخدم'), [':v1' => e(number_format($role->users_count))]) !!}
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @can('roles.create')
        <x-modal id="new-role-modal" :title="setting('admin.roles.index.dwr_jdyd_nskh_qalb', 'دور جديد = نسخ قالب')">
            <form method="post" action="{{ route('admin.roles.store') }}" class="space-y-3">
                @csrf
                <p class="text-xs" style="color: var(--text-muted)">
                    {{ setting('admin.roles.index.insha_dwr_jdyd_nskh_qalb_wtadylh_wmsh_hyntql', 'إنشاء دور جديد = نسخ قالب وتعديله — ومش هينتقل للنسخة إلّا اللي إنت نفسك تملكه.') }}
                </p>

                <label class="block text-sm">
                    <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.roles.index.asm_aldwr', 'اسم الدور') }}</span>
                    <input type="text" name="name_ar" required maxlength="120"
                           class="mt-1 w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <label class="block text-sm">
                    <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.roles.index.alqalb_almsdr', 'القالب المصدر') }}</span>
                    <select name="template" required class="mt-1 w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name_ar }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.roles.index.anshy_alnskha', 'أنشئ النسخة') }}</button>
            </form>
        </x-modal>
    @endcan
@endsection

@section('mobile_action')
    @can('roles.create')
        <button type="button" data-modal-open="new-role-modal"
                class="btn w-full flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.roles.index.dwr_jdyd', '+ دور جديد') }}</button>
    @endcan
@endsection
