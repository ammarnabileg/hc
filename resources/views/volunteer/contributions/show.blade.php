@extends('layouts.volunteer')

@section('title', setting('volunteer.contributions_show.title', 'بند مساهمة'))

@section('content')
    <x-page-header
        :title="$contribution->item_title"
        :subtitle="setting('volunteer.contributions_show.subtitle', 'المهمّة الأمّ: ').($task?->title ?? '—')"
        :breadcrumbs="[
            ['label' => setting('volunteer.contributions_show.label', 'مساهماتي'), 'url' => route('volunteer.contributions')],
            ['label' => $contribution->item_title],
        ]" />

    <p class="card p-3 mb-4 text-sm font-bold"
       style="border-inline-start: 3px solid var(--color-state-danger)">
        ◉ {{ setting('volunteer.contributions_show.text', 'عدم التسليم =') }} {{ $penalty }} {{ setting('volunteer.contributions_show.text_2', 'على درجة الالتزام، وعدم الردّ على تفتيش في مهلته مثله.') }}
    </p>

    <div class="grid md:grid-cols-2 gap-4">
        <section class="card p-4 space-y-2 text-sm">
            <h2 class="font-bold mb-2">{{ setting('volunteer.contributions_show.heading', 'تفاصيل البند') }}</h2>
            <p>{{ setting('volunteer.contributions_show.text_3', 'المالك:') }} {{ $owner?->name ?? '—' }}</p>
            <p>{{ setting('volunteer.contributions_show.text_4', 'الديدلاين الداخليّ:') }} {{ $contribution->internal_deadline_at?->format('Y-m-d H:i') }}</p>
            <p>{{ setting('volunteer.contributions_show.text_5', 'ديدلاين المهمّة الأمّ:') }} {{ $task?->deadline_at?->format('Y-m-d H:i') ?? '—' }}</p>
            <p>{{ setting('volunteer.contributions_show.text_6', 'القيمة:') }} {{ (float) $contribution->vxp_value }} VXP</p>
            <details class="mt-2">
                <summary class="text-xs cursor-pointer" style="color: var(--text-muted)">{{ setting('volunteer.common.output_format', 'شكل المخرجات') }}</summary>
                <p class="mt-2 whitespace-pre-line">{{ $contribution->deliverable_spec ?: setting('volunteer.contributions_show.text_7', 'لم يُحدَّد.') }}</p>
            </details>
        </section>

        <section class="card p-4 text-sm">
            <h2 class="font-bold mb-2">{{ setting('volunteer.contributions_show.heading_2', 'نقاط التفتيش') }}</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted)">{{ $activityWindow }} — {{ setting('volunteer.contributions_show.text_8', 'وما خارجها لا يُحتسَب تأخيرًا.') }}</p>

            @forelse ($checkpoints as $point)
                <div class="flex items-center justify-between gap-2 py-2" style="border-top: 1px solid var(--border)">
                    <span>{{ setting('volunteer.contributions_show.text_9', 'تفتيش') }} {{ $point->sequence }} — {{ $point->scheduled_at?->format('Y-m-d H:i') }}</span>
                    <x-state-badge :state="$point->status === 'answered' ? 'ok' : ($point->status === 'missed' ? 'danger' : 'warn')" />
                </div>
            @empty
                <p style="color: var(--text-muted)">{{ setting('volunteer.contributions_show.text_10', 'بلا نقاط تفتيش على البند ده.') }}</p>
            @endforelse
        </section>
    </div>
@endsection
