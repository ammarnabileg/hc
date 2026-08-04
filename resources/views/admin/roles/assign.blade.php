@extends('layouts.admin')

@section('title', setting('admin.roles.assign.isnad_dwr', 'إسناد دور'))

@section('content')
    <x-page-header :title="setting('admin.roles.assign.isnad_dwr', 'إسناد دور')"
                   :subtitle="setting('admin.roles.assign_hint', 'الدور يحدّد «ماذا» والعضويّة تحدّد «أين».')"
                   :breadcrumbs="[
                       ['label' => setting('admin.roles.assign.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')],
                       ['label' => setting('admin.roles.assign.aladwar_walslahyat', 'الأدوار والصلاحيّات'), 'url' => route('admin.roles.index')],
                       ['label' => setting('admin.roles.assign.isnad_dwr', 'إسناد دور')],
                   ]" />

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="card p-4">
            <h3 class="font-bold text-sm mb-3">{{ setting('admin.roles.assign.isnad_jdyd', 'إسناد جديد') }}</h3>

            {{-- اختيار المستخدم أوّلًا ليُقرأ منه عضويّاته (قفص العضويّة) --}}
            <form method="get" class="flex flex-wrap items-end gap-3 mb-4">
                <label class="flex flex-col gap-1 flex-1 min-w-[12rem]">
                    <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.roles.assign.rqm_almstkhdm', 'رقم المستخدم') }}</span>
                    <input type="number" name="user" value="{{ $target?->id }}" min="1" required
                           class="rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
                <button type="submit" class="rounded-xl px-4 py-2 text-sm motion-standard"
                        style="background: var(--surface-sunken)">{{ setting('admin.roles.assign.aard_adwyath', 'اعرض عضويّاته') }}</button>
            </form>

            @if (! $target)
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.roles.assign.akhtr_almstkhdm_alawl_ashan_nard_adwyath', 'اختر المستخدم الأوّل عشان نعرض عضويّاته.') }}</p>
            @else
                <div class="rounded-xl p-3 mb-3 text-sm" style="background: var(--surface-sunken)">
                    {{ $target->name }} — #{{ $target->code }}
                </div>

                <form method="post" action="{{ route('admin.roles.assign.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="user" value="{{ $target->id }}">

                    <label class="block text-sm">
                        <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.roles.assign.aldwr_madha', 'الدور (ماذا)') }}</span>
                        <select name="role" required class="mt-1 w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">
                                    {{ $role->name_ar }}@if ($role->requires_membership) {{ setting('admin.roles.assign.yhtaj_adwya', '— يحتاج عضويّة') }} @endif
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.roles.assign.aladwya_ayn', 'العضويّة (أين)') }}</span>
                        <select name="membership" class="mt-1 w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">{{ setting('admin.roles.assign.bla_adwya_dwr_mnsa', 'بلا عضويّة (دور منصّة)') }}</option>
                            @foreach ($memberships as $membership)
                                <option value="{{ $membership->id }}">
                                    {{ $membership->entity?->name_ar ?? setting('admin.roles.assign.kyan', 'كيان') }} — {{ $membership->position?->name_ar ?? setting('admin.roles.assign.bwzshn', 'بوزشن') }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.roles.assign.isnad', 'إسناد') }}</button>
                </form>
            @endif
        </section>

        <section class="card p-4">
            <h3 class="font-bold text-sm mb-3">{{ setting('admin.roles.assign.akhr_alisnadat', 'آخر الإسنادات') }}</h3>

            @if ($assignments->isEmpty())
                <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.roles.assign.mafysh_isnadat_lsh', 'مافيش إسنادات لسّه.') }}</p>
            @else
                <ul class="divide-y" style="border-color: var(--border)">
                    @foreach ($assignments as $assignment)
                        <li class="flex flex-wrap items-center gap-2 py-3" style="border-color: var(--border)">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold truncate">{{ $assignment->user?->shortName() ?? '—' }}</div>
                                <div class="text-xs truncate" style="color: var(--text-muted)">
                                    {{ $assignment->role?->name_ar }}
                                    @if ($assignment->membership)
                                        · {{ $assignment->membership->entity?->name_ar }} / {{ $assignment->membership->position?->name_ar }}
                                    @endif
                                </div>
                            </div>

                            @include('admin.roles.partials.audit-hover', ['log' => $lastChanges[$assignment->user_id] ?? null])

                            <form method="post" action="{{ route('admin.roles.assign.destroy', $assignment) }}">
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
