@extends('layouts.admin')

@section('title', $meeting->title)

@php
    /**
     * تفاصيل اجتماع في المرآة الإداريّة (24.2-أوّلًا) — الحضور بأسمائه وقيمه
     * والمحضر. لا نُكرّر شاشة المتطوّع: هنا **مراجعة** لا مشاركة.
     */
@endphp

@section('content')
    <x-page-header :title="$meeting->title"
                   :subtitle="($meeting->entity?->name_ar ?? setting('admin.meetings_admin.show.kl_almttwayn', 'كلّ المتطوّعين')).' · '.($meeting->owner?->name ?? '')"
                   :breadcrumbs="[
                       ['label' => setting('admin.meetings_admin.show.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')],
                       ['label' => setting('admin.meetings_admin.show.ajtmaaat_alttwa', 'اجتماعات التطوّع'), 'url' => route('admin.meetings.index')],
                       ['label' => setting('admin.meetings_admin.show.tfasyl', 'تفاصيل')],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.meetings.index') }}"
               class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.meetings_admin.show.rjwa', 'رجوع') }}</a>
        </x-slot:action>
    </x-page-header>

    <div class="card p-4 mb-4">
        <dl class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.show.almwad', 'الموعد') }}</dt><dd>{{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</dd></div>
            <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.show.alhala', 'الحالة') }}</dt>
                <dd><x-state-badge :state="$meeting->status === 'ended' ? 'ok' : 'idle'" :label="$meeting->status === 'ended' ? setting('admin.meetings_admin.show.mnth', 'منتهٍ') : setting('admin.meetings_admin.show.qadm', 'قادم')" /></dd>
            </div>
            <div><dt class="text-xs" style="color: var(--text-muted)">{{ setting('admin.meetings_admin.show.nafdha_altsjyl', 'نافذة التسجيل') }}</dt>
                <dd>
                    @if ($attendance->windowOpen($meeting))
                        <x-state-badge :state="$attendance->tierState($meeting)"
                                       :label="setting('admin.meetings_admin.show.tqfl', 'تقفل ').$meeting->attendance_closes_at?->diffForHumans()" />
                    @else
                        <span style="color: var(--text-muted)">{{ setting('admin.meetings_admin.show.mqfwla', 'مقفولة') }}</span>
                    @endif
                </dd>
            </div>
        </dl>

        @if (filled($meeting->minutes))
            <div class="mt-4 pt-4" style="border-top: 1px solid var(--border)">
                <h2 class="text-sm font-bold mb-2">{{ setting('admin.meetings_admin.show.almhdr', 'المحضر') }}</h2>
                <p class="text-sm whitespace-pre-line" style="color: var(--text-muted)">{{ $meeting->minutes }}</p>
            </div>
        @endif
    </div>

    @if ($attendees->isEmpty())
        <x-empty :message="setting('admin.meetings_admin.show.mhdsh_sjl_hdwrh_lsh', 'محدّش سجّل حضوره لسّه.')" />
    @else
        <div class="card p-0 overflow-hidden hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.show.aladw', 'العضو') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.show.alhala', 'الحالة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.show.wqt_altsjyl', 'وقت التسجيل') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.meetings_admin.show.drja_alaltzam', 'درجة الالتزام') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attendees as $row)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3">{{ $row->user?->name }} <span class="text-xs" style="color: var(--text-muted)">{{ $row->user?->code }}</span></td>
                            <td class="px-4 py-3">
                                <x-state-badge :state="match ($row->status) { 'registered' => 'ok', 'excused_absence' => 'idle', default => 'danger' }"
                                               :label="match ($row->status) { 'registered' => setting('admin.meetings_admin.show.hadr', 'حاضر'), 'excused_absence' => setting('admin.meetings_admin.show.ghyab_baatdhar', 'غياب باعتذار'), default => setting('admin.meetings_admin.show.ghyab_bla_aatdhar', 'غياب بلا اعتذار') }" />
                            </td>
                            <td class="px-4 py-3 text-xs">{{ $row->registered_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs">{{ $attendance->valueLabel((float) $row->rep_value) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="grid gap-3 md:hidden">
            @foreach ($attendees as $row)
                <article class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold">{{ $row->user?->name }}</p>
                        <x-state-badge :state="match ($row->status) { 'registered' => 'ok', 'excused_absence' => 'idle', default => 'danger' }"
                                       :label="match ($row->status) { 'registered' => setting('admin.meetings_admin.show.hadr', 'حاضر'), 'excused_absence' => setting('admin.meetings_admin.show.ghyab_baatdhar', 'غياب باعتذار'), default => setting('admin.meetings_admin.show.ghyab_bla_aatdhar', 'غياب بلا اعتذار') }" />
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-muted)">
                        {{ $row->registered_at?->format('Y-m-d H:i') ?? setting('admin.meetings_admin.show.lm_ysjl', 'لم يسجّل') }} · {{ $attendance->valueLabel((float) $row->rep_value) }}
                    </p>
                </article>
            @endforeach
        </div>
    @endif
@endsection
