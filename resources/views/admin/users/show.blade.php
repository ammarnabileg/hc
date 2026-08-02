@extends('layouts.admin')

@section('title', $user->shortName())

@section('content')
    <x-page-header :title="$user->name"
                   :subtitle="'#'.$user->code"
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')],
                       ['label' => 'المستخدمون', 'url' => route('admin.users.index')],
                       ['label' => $user->shortName()],
                   ]">
        <x-slot:action>
            <x-state-badge :state="$directory->statusState($user->status)"
                           :label="\App\Services\Admin\UserDirectory::STATUSES[$user->status] ?? $user->status" />
        </x-slot:action>
    </x-page-header>

    {{-- تابات 12.1: بيانات · الجداول · أرصدة · تدريبات · شهادات · الأمان · الإدارة · متقدّم · التطوّع --}}
    <x-tabs :tabs="$tabs" :current="$tab" />

    @switch($tab)
        @case('tables')
            @include('admin.users.partials.tab-tables')
            @break

        @case('security')
            @include('admin.users.partials.tab-security')
            @break

        @case('admin')
            @include('admin.users.partials.tab-admin')
            @break

        @case('wallet')
            <section class="card p-4">
                <h3 class="font-bold text-sm mb-3">الأرصدة</h3>
                <div class="flex flex-wrap gap-3">
                    @forelse ($balances as $balance)
                        <div class="rounded-xl px-4 py-3" style="background: var(--surface-sunken)">
                            <div class="text-xs" style="color: var(--text-muted)">{{ $balance->currency?->name_ar }}</div>
                            <div class="font-extrabold">{{ number_format((float) $balance->balance) }}</div>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">لسّه مافيش رصيد.</p>
                    @endforelse
                </div>

                {{-- تفاصيل المعاملات والسحوبات في تاب «الجداول» بفلتر الفترة (12.1) --}}
                <a href="{{ route('admin.users.show', ['user' => $user, 'tab' => 'tables']) }}"
                   class="inline-block mt-4 text-xs hover:underline" style="color: var(--color-brand-500)">
                    شوف المعاملات والسحوبات بفلتر الفترة
                </a>
            </section>
            @break

        @case('learning')
            <section class="card p-4">
                <h3 class="font-bold text-sm mb-3">التدريبات</h3>
                @if ($enrollments->isEmpty())
                    <p class="text-sm" style="color: var(--text-muted)">لسّه مابدأش تدريب.</p>
                @else
                    <ul class="divide-y" style="border-color: var(--border)">
                        @foreach ($enrollments as $enrollment)
                            <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                                <span class="flex-1 min-w-0 truncate">{{ $enrollment->course?->name_ar ?? '—' }}</span>
                                <span class="text-xs" style="color: var(--text-muted)">{{ $enrollment->created_at?->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
            @break

        @case('certificates')
            <section class="card p-4">
                <h3 class="font-bold text-sm mb-3">الشهادات</h3>
                @if ($certificates->isEmpty())
                    <p class="text-sm" style="color: var(--text-muted)">مافيش شهادات صادرة.</p>
                @else
                    <ul class="divide-y" style="border-color: var(--border)">
                        @foreach ($certificates as $certificate)
                            <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                                <span class="flex-1 min-w-0 truncate">{{ $certificate->number ?? '—' }}</span>
                                <x-state-badge state="honor" label="شهادة" />
                                <span class="text-xs" style="color: var(--text-muted)">{{ $certificate->created_at?->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
            @break

        @case('advanced')
            <div class="grid gap-4 lg:grid-cols-2">
                {{-- أدوات احتواء الحساب المسيء + التصفّح كمستخدم + التصدير (12.1-متقدّم) --}}
                @include('admin.moderation.panel')

                {{-- تثبيت/تصحيح الدولة يدويًّا (12.1-متقدّم-5) --}}
                @if (auth()->user()->allows('admin_user_detail.edit'))
                    <section class="card p-4">
                        <h3 class="font-bold text-sm mb-1">الدولة</h3>
                        <p class="text-xs mb-3" style="color: var(--text-muted)">
                            {{ setting('admin.users.country_pin_hint', 'الكشف التلقائيّ بيتبع مكانه دلوقتي — والتثبيت اليدويّ بيعلو عليه ومابيتدهسش.') }}
                        </p>

                        <form method="post" action="{{ route('admin.users.country', $user) }}" class="space-y-2">
                            @csrf
                            <select name="country_id" class="w-full rounded-xl px-3 py-2 text-sm"
                                    style="min-block-size: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                <option value="">— بلا تثبيت (كشف تلقائيّ) —</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}" @selected($user->country_id === $country->id)>{{ $country->name_ar }}</option>
                                @endforeach
                            </select>

                            @if ($user->country_locked_at)
                                <p class="text-xs" style="color: var(--text-muted)">
                                    مثبَّتة من {{ \Illuminate\Support\Carbon::parse($user->country_locked_at)->translatedFormat('Y-m-d') }}
                                </p>
                            @endif

                            <button class="btn w-full rounded-xl py-2 text-sm font-semibold motion-standard"
                                    style="min-block-size: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                تثبيت الدولة
                            </button>
                        </form>
                    </section>
                @endif

                @if ($lastChange)
                    <section class="card p-4 lg:col-span-2">
                        <h3 class="font-bold text-sm mb-3">آخر تغيير</h3>
                        @include('admin.roles.partials.audit-hover', ['log' => $lastChange])
                    </section>
                @endif
            </div>
            @break

        @case('volunteer')
            {{-- سجلّ المشرف — يملأه مجال التطوّع، ويظهر التاب لمن له صلاحيّته وحده (12.1) --}}
            <section class="card p-4">
                <h3 class="font-bold text-sm mb-3">سجلّ التطوّع</h3>
                @stack('admin_user_volunteer_tab')
            </section>
            @break

        @default
            @include('admin.users.partials.tab-profile')
    @endswitch
@endsection
