@extends('layouts.admin')

@section('title', setting('admin.report_schedules.log.sjl_irsal', 'سجلّ إرسال: ').$schedule->name)

@php
    /**
     * سجلّ الإرسال (24.3-خامسًا) — يُكتَب ولا يُعاد كتابته، فهو المرجع الوحيد
     * الذي يجيب عن «وصل ولّا مَوَصَلْش، وليه؟».
     */
@endphp

@section('content')
    <x-page-header :title="setting('admin.report_schedules.log.sjl_irsal', 'سجلّ إرسال: ').$schedule->name"
                   :subtitle="setting('admin.report_schedules.log.kl_tshghyl_bntyjth_wsbbh_alnjah_walfshl', 'كلّ تشغيل بنتيجته وسببه — النجاح والفشل والفراغ.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.report_schedules.log.lwha_alidara', 'لوحة الإدارة'), 'url' => route('admin.dashboard')],
                       ['label' => setting('admin.report_schedules.log.altqaryr_almjdwla', 'التقارير المجدولة'), 'url' => route('admin.report-schedules.index')],
                       ['label' => setting('admin.report_schedules.log.sjl_alirsal', 'سجلّ الإرسال')],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.report-schedules.index') }}"
               class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ setting('admin.report_schedules.log.rjwa', 'رجوع') }}</a>
        </x-slot:action>
    </x-page-header>

    @if ($runs->isEmpty())
        <x-empty :message="setting('admin.report_schedules.log.aljdwla_dy_mashtghltsh_lsh_htlaqy_awl_str', 'الجدولة دي ماشتغلتش لسّه — هتلاقي أوّل سطر هنا بعد أوّل إرسال.')" />
    @else
        <div class="card p-0 overflow-hidden hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.report_schedules.log.alwqt', 'الوقت') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.report_schedules.log.alntyja', 'النتيجة') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.report_schedules.log.alsfwf', 'الصفوف') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.report_schedules.log.almstqblwn', 'المستقبِلون') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.report_schedules.log.altfsyl', 'التفصيل') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3 text-xs">
                                {{ $run->ran_at?->format('Y-m-d H:i') }}
                                @if ($run->was_manual)
                                    <div style="color: var(--text-muted)">{{ setting('admin.report_schedules.log.ydwy', 'يدويّ ·') }} {{ $run->triggeredBy?->name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-state-badge :state="match ($run->result) { 'sent' => 'ok', 'empty' => 'idle', default => 'danger' }"
                                               :label="match ($run->result) { 'sent' => setting('admin.report_schedules.log.wsl', 'وصل'), 'empty' => setting('admin.report_schedules.log.fady', 'فاضي'), default => setting('admin.report_schedules.log.fshl', 'فشل') }" />
                            </td>
                            <td class="px-4 py-3">{{ $run->rows_count }}</td>
                            <td class="px-4 py-3">{{ $run->recipients_count }}</td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-muted)">
                                {{ $run->message }}
                                {{-- الرابط المؤقّت يظهر فقط ما دام شغّالًا — والمنتهي يُخفى لا يُعطَّل (2.15-أ-7) --}}
                                @if ($run->hasLiveDownload())
                                    <a href="{{ $run->downloadUrl() }}" class="block mt-1 font-semibold" style="color: var(--brand)">
                                        {!! strtr(setting('admin.report_schedules.log.nzl_almlf_v1_lhd', 'نزّل الملفّ (:v1) — لحدّ'), [':v1' => e(strtoupper($run->download_format))]) !!} {{ $run->download_expires_at->format('Y-m-d H:i') }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="grid gap-3 md:hidden">
            @foreach ($runs as $run)
                <article class="card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold">{{ $run->ran_at?->format('Y-m-d H:i') }}</p>
                        <x-state-badge :state="match ($run->result) { 'sent' => 'ok', 'empty' => 'idle', default => 'danger' }"
                                       :label="match ($run->result) { 'sent' => setting('admin.report_schedules.log.wsl', 'وصل'), 'empty' => setting('admin.report_schedules.log.fady', 'فاضي'), default => setting('admin.report_schedules.log.fshl', 'فشل') }" />
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-muted)">
                        {{ $run->rows_count }} {!! strtr(setting('admin.report_schedules.log.sfa_v1_mstqbl', 'صفًّا · :v1 مستقبِل'), [':v1' => e($run->recipients_count)]) !!}
                        @if ($run->was_manual) {{ setting('admin.report_schedules.log.ydwy_2', '· يدويّ (') }}{{ $run->triggeredBy?->name }}) @endif
                    </p>
                    @if ($run->message)
                        <p class="text-xs mt-2" style="color: var(--text-muted)">{{ $run->message }}</p>
                    @endif
                    @if ($run->hasLiveDownload())
                        <a href="{{ $run->downloadUrl() }}" class="block text-xs mt-2 font-semibold" style="color: var(--brand)">
                            {!! strtr(setting('admin.report_schedules.log.nzl_almlf_v1_lhd', 'نزّل الملفّ (:v1) — لحدّ'), [':v1' => e(strtoupper($run->download_format))]) !!} {{ $run->download_expires_at->format('Y-m-d H:i') }}
                        </a>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="mt-4">{{ $runs->links() }}</div>
    @endif
@endsection
