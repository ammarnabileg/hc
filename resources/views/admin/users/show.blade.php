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

    {{-- تابات: بيانات · محفظة ومعاملات · تدريبات · شهادات · متقدّم · التطوّع (12.1) --}}
    <x-tabs :tabs="$tabs" :current="$tab" />

    @switch($tab)
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

                <h3 class="font-bold text-sm mt-5 mb-2">آخر المعاملات</h3>
                @if ($transactions->isEmpty())
                    <p class="text-sm" style="color: var(--text-muted)">مافيش معاملات في السجلّ.</p>
                @else
                    <ul class="divide-y" style="border-color: var(--border)">
                        @foreach ($transactions as $transaction)
                            <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                                <span class="flex-1 min-w-0 truncate">{{ $transaction->reason ?? $transaction->source }}</span>
                                <span class="font-semibold">{{ number_format((float) $transaction->amount, 2) }} {{ $transaction->currency?->name_ar }}</span>
                                <span class="text-xs" style="color: var(--text-muted)">{{ $transaction->created_at?->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
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
                <section class="card p-4">
                    <h3 class="font-bold text-sm mb-3">الأدوار</h3>
                    <ul class="space-y-1 text-sm">
                        @forelse ($user->roles as $role)
                            <li class="flex items-center justify-between gap-2">
                                <span>{{ $role->name_ar }}</span>
                                <span class="text-xs" style="color: var(--text-muted)">
                                    {{ $role->pivot->membership_id ? 'داخل عضويّة #'.$role->pivot->membership_id : 'دور منصّة' }}
                                </span>
                            </li>
                        @empty
                            <li style="color: var(--text-muted)">مافيش أدوار مسنَدة.</li>
                        @endforelse
                    </ul>

                    @if (auth()->user()->allows('roles.assign'))
                        <a href="{{ route('admin.roles.assign', ['user' => $user->id]) }}"
                           class="inline-block mt-3 text-xs hover:underline" style="color: var(--color-brand-500)">إسناد دور</a>
                    @endif
                </section>

                <section class="card p-4">
                    <h3 class="font-bold text-sm mb-3">الدعوات</h3>
                    @if ($referrals->isEmpty())
                        <p class="text-sm" style="color: var(--text-muted)">مادعاش حدّ لسّه.</p>
                    @else
                        <ul class="divide-y" style="border-color: var(--border)">
                            @foreach ($referrals as $referral)
                                <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                                    <span class="flex-1 min-w-0 truncate">{{ $referral->referred?->shortName() ?? 'لسّه ماسجّلش' }}</span>
                                    <x-state-badge :state="$referral->welcome_ticket_granted ? 'ok' : 'idle'"
                                                   :label="$referral->welcome_ticket_granted ? 'خد هديته' : 'لسّه'" />
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($lastChange)
                        <div class="mt-4">
                            @include('admin.roles.partials.audit-hover', ['log' => $lastChange])
                        </div>
                    @endif
                </section>

                {{-- أدوات احتواء الحساب المسيء (12.1) --}}
                @include('admin.moderation.panel')
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
            <section class="card p-4">
                <h3 class="font-bold text-sm mb-3">البيانات الأساسيّة</h3>
                <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-xs" style="color: var(--text-muted)">الاسم</dt><dd>{{ $user->name }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">الكود</dt><dd>#{{ $user->code }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">البريد</dt><dd>{{ $directory->mask($user->email, 'email') }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">الموبايل</dt><dd>{{ $directory->mask($user->phone, 'phone') }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">الدولة</dt><dd>{{ $user->country?->name_ar ?? '—' }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">المحافظة</dt><dd>{{ $user->governorate?->name_ar ?? '—' }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">XP</dt><dd>{{ number_format((int) $user->xp) }}</dd></div>
                    <div><dt class="text-xs" style="color: var(--text-muted)">تاريخ التسجيل</dt><dd>{{ $user->created_at?->format('Y-m-d') }}</dd></div>
                </dl>
            </section>
    @endswitch
@endsection
