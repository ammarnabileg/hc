@extends('layouts.app')

@section('title', 'بند مساهمة')

@section('content')
    <x-page-header
        :title="$contribution->item_title"
        :subtitle="'المهمّة الأمّ: '.($task?->title ?? '—')"
        :breadcrumbs="[
            ['label' => 'مساهماتي', 'url' => route('volunteer.contributions')],
            ['label' => $contribution->item_title],
        ]" />

    <p class="card p-3 mb-4 text-sm font-bold"
       style="border-inline-start: 3px solid var(--color-state-danger)">
        ◉ عدم التسليم = {{ $penalty }} على درجة الالتزام، وعدم الردّ على تفتيش في مهلته مثله.
    </p>

    <div class="grid md:grid-cols-2 gap-4">
        <section class="card p-4 space-y-2 text-sm">
            <h2 class="font-bold mb-2">تفاصيل البند</h2>
            <p>المالك: {{ $owner?->name ?? '—' }}</p>
            <p>الديدلاين الداخليّ: {{ $contribution->internal_deadline_at?->format('Y-m-d H:i') }}</p>
            <p>ديدلاين المهمّة الأمّ: {{ $task?->deadline_at?->format('Y-m-d H:i') ?? '—' }}</p>
            <p>القيمة: {{ (float) $contribution->vxp_value }} VXP</p>
            <details class="mt-2">
                <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">شكل المخرجات</summary>
                <p class="mt-2 whitespace-pre-line">{{ $contribution->deliverable_spec ?: 'لم يُحدَّد.' }}</p>
            </details>
        </section>

        <section class="card p-4 text-sm">
            <h2 class="font-bold mb-2">نقاط التفتيش</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted)">{{ $activityWindow }} — وما خارجها لا يُحتسَب تأخيرًا.</p>

            @forelse ($checkpoints as $point)
                <div class="flex items-center justify-between gap-2 py-2" style="border-top: 1px solid var(--border)">
                    <span>تفتيش {{ $point->sequence }} — {{ $point->scheduled_at?->format('Y-m-d H:i') }}</span>
                    <x-state-badge :state="$point->status === 'answered' ? 'ok' : ($point->status === 'missed' ? 'danger' : 'warn')" />
                </div>
            @empty
                <p style="color: var(--text-muted)">بلا نقاط تفتيش على البند ده.</p>
            @endforelse
        </section>
    </div>
@endsection
