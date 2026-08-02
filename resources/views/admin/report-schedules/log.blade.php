@extends('layouts.admin')

@section('title', 'سجلّ إرسال: '.$schedule->name)

@php
    /**
     * سجلّ الإرسال (24.3-خامسًا) — يُكتَب ولا يُعاد كتابته، فهو المرجع الوحيد
     * الذي يجيب عن «وصل ولّا مَوَصَلْش، وليه؟».
     */
@endphp

@section('content')
    <x-page-header :title="'سجلّ إرسال: '.$schedule->name"
                   subtitle="كلّ تشغيل بنتيجته وسببه — النجاح والفشل والفراغ."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => route('admin.dashboard')],
                       ['label' => 'التقارير المجدولة', 'url' => route('admin.report-schedules.index')],
                       ['label' => 'سجلّ الإرسال'],
                   ]">
        <x-slot:action>
            <a href="{{ route('admin.report-schedules.index') }}"
               class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">رجوع</a>
        </x-slot:action>
    </x-page-header>

    @if ($runs->isEmpty())
        <x-empty message="الجدولة دي ماشتغلتش لسّه — هتلاقي أوّل سطر هنا بعد أوّل إرسال." />
    @else
        <div class="card p-0 overflow-hidden hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken)">
                        <th class="text-start px-4 py-3 font-semibold">الوقت</th>
                        <th class="text-start px-4 py-3 font-semibold">النتيجة</th>
                        <th class="text-start px-4 py-3 font-semibold">الصفوف</th>
                        <th class="text-start px-4 py-3 font-semibold">المستقبِلون</th>
                        <th class="text-start px-4 py-3 font-semibold">التفصيل</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3 text-xs">
                                {{ $run->ran_at?->format('Y-m-d H:i') }}
                                @if ($run->was_manual)
                                    <div style="color: var(--text-muted)">يدويّ · {{ $run->triggeredBy?->name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-state-badge :state="match ($run->result) { 'sent' => 'ok', 'empty' => 'idle', default => 'danger' }"
                                               :label="match ($run->result) { 'sent' => 'وصل', 'empty' => 'فاضي', default => 'فشل' }" />
                            </td>
                            <td class="px-4 py-3">{{ $run->rows_count }}</td>
                            <td class="px-4 py-3">{{ $run->recipients_count }}</td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-muted)">{{ $run->message }}</td>
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
                                       :label="match ($run->result) { 'sent' => 'وصل', 'empty' => 'فاضي', default => 'فشل' }" />
                    </div>
                    <p class="text-xs mt-2" style="color: var(--text-muted)">
                        {{ $run->rows_count }} صفًّا · {{ $run->recipients_count }} مستقبِل
                        @if ($run->was_manual) · يدويّ ({{ $run->triggeredBy?->name }}) @endif
                    </p>
                    @if ($run->message)
                        <p class="text-xs mt-2" style="color: var(--text-muted)">{{ $run->message }}</p>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="mt-4">{{ $runs->links() }}</div>
    @endif
@endsection
