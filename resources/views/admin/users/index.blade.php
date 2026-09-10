@extends('layouts.admin')

@section('title', setting('admin.users.index.almstkhdmwn', 'المستخدمون'))

@section('content')
    <x-page-header :title="setting('admin.users.index.almstkhdmwn', 'المستخدمون')"
                   :subtitle="setting('admin.users.index.ijmaly', 'إجمالي ').number_format($users->total()).setting('admin.users.index.hsab', ' حساب')"
                   :breadcrumbs="[['label' => setting('admin.users.index.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')], ['label' => setting('admin.users.index.almstkhdmwn', 'المستخدمون')]]">
        <x-slot:action>
            {{-- زرّ «أعمدة»: الباقي موجود ومخفيّ، والاختيار يُحفَظ للمستخدم (2.15-د-⭐) --}}
            <button type="button" data-modal-open="columns-modal"
                    class="rounded-xl px-3 py-2 text-sm motion-standard"
                    style="background: var(--surface-raised)">{{ setting('admin.users.index.aamda', 'أعمدة') }}</button>
        </x-slot:action>
    </x-page-header>

    {{-- ثلاثة فلاتر ظاهرة + بحث، والباقي مطويّ (2.15-أ-4) --}}
    <x-filters :action="route('admin.users.index')">
        <label class="flex flex-col gap-1">
            <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.index.bhth_balkwd_aw_alasm_aw_albryd_aw_almwbayl', 'بحث بالكود أو الاسم أو البريد أو الموبايل') }}</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ setting('admin.users.index.aktb_ay_haja_tarfha', 'اكتب أيّ حاجة تعرفها…') }}"
                   class="rounded-xl px-3 py-2 text-sm w-64 max-w-full"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="flex flex-col gap-1">
            <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.index.alhala', 'الحالة') }}</span>
            <select name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.users.index.alkl', 'الكلّ') }}</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex flex-col gap-1">
            <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.index.aldwr', 'الدور') }}</span>
            <select name="role" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.users.index.alkl', 'الكلّ') }}</option>
                @foreach ($roles as $key => $label)
                    <option value="{{ $key }}" @selected(request('role') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex flex-col gap-1">
            <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.index.altsjyl_khlal', 'التسجيل خلال') }}</span>
            <select name="days" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.users.index.ay_wqt', 'أيّ وقت') }}</option>
                @foreach ([7, 30, 90] as $option)
                    <option value="{{ $option }}" @selected(request('days') == $option)>{!! strtr(setting('admin.users.index.akhr_v1_ywm', 'آخر :v1 يوم'), [':v1' => e($option)]) !!}</option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.users.index.fltra', 'فلترة') }}</button>

        <x-slot:advanced>
            <label class="flex flex-col gap-1">
                <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.index.adna_xp', 'أدنى XP') }}</span>
                <input type="number" name="min_xp" value="{{ request('min_xp') }}" min="0"
                       class="rounded-xl px-3 py-2 text-sm w-32"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>

            <label class="flex flex-col gap-1">
                <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.index.mazhrsh_mn_ywm', 'ماظهرش من (يوم)') }}</span>
                <input type="number" name="idle_days" value="{{ request('idle_days') }}" min="1"
                       class="rounded-xl px-3 py-2 text-sm w-32"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($users->isEmpty())
        {{-- تمييز «مفيش مستخدمين أصلًا» عن «الفلتر ما طابقش حاجة» (24.2) --}}
        <x-empty :message="setting('admin.users.empty_message', 'مفيش نتائج — امسح الفلاتر وجرّب تاني')"
                 :action="setting('admin.users.index.amsh_alflatr', 'امسح الفلاتر')" :href="route('admin.users.index')"
                 :filtered="request('q') || request('status') || request('role') || request('days') || request('min_xp') || request('idle_days')" />
    @else
        {{-- سطح المكتب: جدول بأعمدته الافتراضيّة (5–7) --}}
        <div class="card p-0 overflow-hidden hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        @foreach ($columns as $column)
                            <th class="text-start px-4 py-3 font-semibold">{{ $allColumns[$column] }}</th>
                        @endforeach
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $row)
                        <tr class="border-t" style="border-color: var(--border)">
                            @foreach ($columns as $column)
                                <td class="px-4 py-3">@include('admin.users.partials.cell', ['row' => $row, 'column' => $column])</td>
                            @endforeach
                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('admin.users.show', $row) }}" class="text-xs hover:underline"
                                   style="color: var(--color-brand-500)">{{ setting('admin.users.index.fth', 'فتح') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- الموبايل: كروت رأسيّة بأهمّ الحقول والباقي بالتوسيع — ممنوع التمرير الأفقيّ (2.15-ج) --}}
        <div class="md:hidden space-y-3">
            @foreach ($users as $row)
                <div class="card p-3">
                    <div class="flex items-center gap-3">
                        <x-avatar :user="$row" size="9" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('admin.users.show', $row) }}" class="block truncate font-semibold">{{ $row->shortName() }}</a>
                            <div class="text-xs" style="color: var(--text-muted)">#{{ $row->code }}</div>
                        </div>
                        <x-state-badge :state="$directory->statusState($row->status)"
                                       :label="$statuses[$row->status] ?? $row->status" />
                    </div>

                    <details class="mt-2">
                        <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">{{ setting('admin.users.index.tfasyl_aktr', 'تفاصيل أكتر') }}</summary>
                        <dl class="mt-2 space-y-1 text-xs">
                            @foreach ($columns as $column)
                                @continue(in_array($column, ['name', 'code', 'status'], true))
                                <div class="flex justify-between gap-2">
                                    <dt style="color: var(--text-muted)">{{ $allColumns[$column] }}</dt>
                                    <dd>@include('admin.users.partials.cell', ['row' => $row, 'column' => $column])</dd>
                                </div>
                            @endforeach
                        </dl>
                    </details>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $users->links() }}</div>
    @endif

    <x-modal id="columns-modal" :title="setting('admin.users.index.aamda_aljdwl', 'أعمدة الجدول')">
        <form method="post" action="{{ route('admin.users.columns') }}" class="space-y-3">
            @csrf
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('admin.users.index.akhtyark_bythfz_lk_int_whdk', 'اختيارك بيتحفظ لك إنت وحدك.') }}</p>

            <div class="grid grid-cols-2 gap-2">
                @foreach ($allColumns as $key => $label)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="columns[]" value="{{ $key }}" @checked(in_array($key, $columns, true))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.users.index.hfz_alaamda', 'حفظ الأعمدة') }}</button>
        </form>
    </x-modal>
@endsection
