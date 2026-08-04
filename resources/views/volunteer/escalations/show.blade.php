@extends('layouts.volunteer')

@section('title', setting('volunteer.escalations_show.title', 'حالة على محرّك التصعيد'))

@section('content')
    <x-page-header
        :title="$decisions ? setting('volunteer.escalations_show.tooltip', 'قرار مطلوب') : setting('volunteer.escalations_show.tooltip_2', 'حالة')"
        :subtitle="$escalation->case_type"
        :breadcrumbs="[
            ['label' => setting('volunteer.escalations_show.label', 'يحتاج قرارك'), 'url' => route('volunteer.escalations')],
            ['label' => setting('volunteer.escalations_show.label_2', 'تفاصيل الحالة')],
        ]" />

    <section class="card p-4 space-y-2 text-sm">
        <p>{{ setting('volunteer.escalations_show.text', 'المستوى الحاليّ:') }} {{ $escalation->level }} — {{ $escalation->is_top_level ? setting('volunteer.escalations_show.text_2', 'السقف (48 ساعة)') : setting('volunteer.escalations_show.text_3', 'نافذة 24 ساعة') }}</p>
        <p>{{ setting('volunteer.escalations_show.text_4', 'تنتهي النافذة:') }} {{ $escalation->window_due_at?->format('Y-m-d H:i') }}</p>
        <p>{{ setting('volunteer.escalations_show.text_5', 'التسوية الآليّة إن فاتت نافذة السقف:') }} <strong>{{ $settlement }}</strong></p>

        @if ($subject?->title)
            <p>{{ setting('volunteer.escalations_show.text_6', 'الموضوع:') }} {{ $subject->title }}</p>
        @endif

        @if (! empty($payload['note']))
            <p class="rounded-xl p-3" style="background: var(--surface-sunken)">{{ $payload['note'] }}</p>
        @endif
    </section>
@endsection
