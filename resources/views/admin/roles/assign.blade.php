@extends('layouts.admin')

@section('title', 'إسناد دور')

@section('content')
    <x-page-header title="إسناد دور"
                   :subtitle="setting('admin.roles.assign_hint', 'الدور يحدّد «ماذا» والعضويّة تحدّد «أين».')"
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')],
                       ['label' => 'الأدوار والصلاحيّات', 'url' => route('admin.roles.index')],
                       ['label' => 'إسناد دور'],
                   ]" />

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="card p-4">
            <h3 class="font-bold text-sm mb-3">إسناد جديد</h3>

            {{-- اختيار المستخدم أوّلًا ليُقرأ منه عضويّاته (قفص العضويّة) --}}
            <form method="get" class="flex flex-wrap items-end gap-3 mb-4">
                <label class="flex flex-col gap-1 flex-1 min-w-[12rem]">
                    <span class="text-xs" style="color: var(--text-muted)">رقم المستخدم</span>
                    <input type="number" name="user" value="{{ $target?->id }}" min="1" required
                           class="rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
                <button type="submit" class="rounded-xl px-4 py-2 text-sm motion-standard"
                        style="background: var(--surface-sunken)">اعرض عضويّاته</button>
            </form>

            @if (! $target)
                <p class="text-sm" style="color: var(--text-muted)">اختر المستخدم الأوّل عشان نعرض عضويّاته.</p>
            @else
                <div class="rounded-xl p-3 mb-3 text-sm" style="background: var(--surface-sunken)">
                    {{ $target->name }} — #{{ $target->code }}
                </div>

                <form method="post" action="{{ route('admin.roles.assign.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="user" value="{{ $target->id }}">

                    <label class="block text-sm">
                        <span class="text-xs" style="color: var(--text-muted)">الدور (ماذا)</span>
                        <select name="role" required class="mt-1 w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">
                                    {{ $role->name_ar }}@if ($role->requires_membership) — يحتاج عضويّة @endif
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-sm">
                        <span class="text-xs" style="color: var(--text-muted)">العضويّة (أين)</span>
                        <select name="membership" class="mt-1 w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">بلا عضويّة (دور منصّة)</option>
                            @foreach ($memberships as $membership)
                                <option value="{{ $membership->id }}">
                                    {{ $membership->entity?->name_ar ?? 'كيان' }} — {{ $membership->position?->name_ar ?? 'بوزشن' }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">إسناد</button>
                </form>
            @endif
        </section>

        <section class="card p-4">
            <h3 class="font-bold text-sm mb-3">آخر الإسنادات</h3>

            @if ($assignments->isEmpty())
                <p class="text-sm" style="color: var(--text-muted)">مافيش إسنادات لسّه.</p>
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
                                <button type="submit" class="text-xs hover:underline" style="color: var(--color-state-danger)">سحب</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
