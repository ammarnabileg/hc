@extends('layouts.admin')

@section('title', setting('admin.events.registrations.almsjlwn_walhdwr', 'المسجّلون والحضور'))

@section('content')
    <x-page-header
        :title="$event->title_ar"
        :subtitle="setting('admin.events.registrations.almsjlwn_withbat_alhdwr_wsrf_almkafaa', 'المسجّلون وإثبات الحضور وصرف المكافأة المتدرّجة.')"
        :breadcrumbs="[
            ['label' => setting('admin.events.registrations.alfaalyat', 'الفعاليّات'), 'url' => route('admin.events.index')],
            ['label' => setting('admin.events.registrations.almsjlwn_walhdwr', 'المسجّلون والحضور')],
        ]" />

    <section class="grid grid-cols-3 gap-3 mb-4">
        <x-kpi :label="setting('admin.events.registrations.msjl', 'مسجّل')" :value="$counts['registered']" icon="edit" />
        <x-kpi :label="setting('admin.events.registrations.hadr', 'حاضر')" :value="$counts['attended']" icon="check" />
        <x-kpi :label="setting('admin.events.registrations.ghayb', 'غائب')" :value="$counts['absent']" icon="○" />
    </section>

    {{-- تشيك-إن يدويّ بكود الحضور --}}
    @can('event_attendance.create')
        <form method="post" action="{{ route('admin.events.check-in', $event) }}" class="card p-4 mb-4">
            @csrf
            <h2 class="font-bold mb-3">{{ setting('admin.events.registrations.tshyk_in_ydwy', 'تشيك-إن يدويّ') }}</h2>

            <div class="flex flex-wrap items-end gap-3">
                <label class="text-sm font-semibold">{{ setting('admin.events.registrations.kwd_almstkhdm', 'كود المستخدم') }}
                    <input type="text" name="code" required maxlength="32"
                           class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
                <label class="text-sm font-semibold">{{ setting('admin.events.registrations.kwd_alhdwr', 'كود الحضور') }}
                    <input type="text" name="attendance_code" required maxlength="32"
                           class="w-full rounded-xl px-3 py-2 text-sm mt-1 font-mono"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.events.registrations.sjl_alhdwr', 'سجّل الحضور') }}</button>
            </div>

            <p class="text-xs mt-2" style="color: var(--text-muted)">
                {{ setting('admin.events.registrations.aldrjat', 'الدرجات:') }} @foreach ($tiers as $tier) {!! strtr(setting('admin.events.registrations.khlal_v1_saaa', 'خلال :v1 ساعة ⟵'), [':v1' => e($tier['hours'])]) !!} {{ $tier['xp'] }} XP{{ ($tier['tickets'] ?? 0) ? ' + '.$tier['tickets'].setting('admin.events.registrations.tdhkra', ' تذكرة') : '' }} @if (! $loop->last) · @endif @endforeach
            </p>
        </form>
    @endcan

    <x-filters :action="route('admin.events.registrations', $event)">
        <div>
            <label class="block text-xs mb-1" for="f-q" style="color: var(--text-muted)">{{ setting('admin.events.registrations.bhth_balasm_alkwd', 'بحث بالاسم/الكود') }}</label>
            <input id="f-q" type="search" name="q" value="{{ $filters['q'] }}"
                   class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </div>
        <div>
            <label class="block text-xs mb-1" for="f-att" style="color: var(--text-muted)">{{ setting('admin.events.registrations.hala_alhdwr', 'حالة الحضور') }}</label>
            <select id="f-att" name="attended" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.events.registrations.alkl', 'الكلّ') }}</option>
                <option value="yes" @selected($filters['attended'] === 'yes')>{{ setting('admin.events.registrations.hdr', 'حضر') }}</option>
                <option value="no" @selected($filters['attended'] === 'no')>{{ setting('admin.events.registrations.ghab', 'غاب') }}</option>
            </select>
        </div>
        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--surface-raised)">{{ setting('admin.events.registrations.fltr', 'فلتر') }}</button>
    </x-filters>

    <section class="card p-4 md:p-5">
        @forelse ($registrations as $registration)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ $registration->user?->name }}
                        <span class="text-xs" style="color: var(--text-muted)">#{{ $registration->user?->code }}</span>
                    </div>
                    <div class="text-xs" style="color: var(--text-muted)">
                        {{ $registration->attend_mode ?? '—' }}
                        @if ($registration->attended_at) {{ setting('admin.events.registrations.tshyk_in', '· تشيك-إن') }} {{ $registration->attended_at->format('Y-m-d H:i') }} @endif
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <x-state-badge :state="$registration->attended ? 'ok' : 'idle'"
                                   :label="$registration->attended ? setting('admin.events.registrations.hdr', 'حضر') : setting('admin.events.registrations.ghab', 'غاب')" />
                    @can('event_attendance.edit')
                        <form method="post" action="{{ route('admin.events.attendance.toggle', $registration) }}">
                            @csrf
                            <button type="submit" class="text-xs underline">{{ $registration->attended ? setting('admin.events.registrations.alm_ghayba', 'علّم غائبًا') : setting('admin.events.registrations.alm_hadra', 'علّم حاضرًا') }}</button>
                        </form>
                    @endcan
                </div>
            </div>
        @empty
            {{-- تمييز «لا مسجّلين أصلًا» عن «الفلتر ما طابقش حاجة» (24.2) --}}
            <x-empty :message="setting('admin.events.registrations.la_msjlyn_bad_shark_rabt_alfaalya', 'لا مسجّلين بعد — شارك رابط الفعاليّة.')"
                     :filtered="$filters['q'] !== '' || $filters['attended'] !== ''" />
        @endforelse
    </section>
@endsection
