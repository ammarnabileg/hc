@extends('layouts.volunteer')

@section('title', 'حالة على محرّك التصعيد')

@section('content')
    <x-page-header
        :title="$decisions ? 'قرار مطلوب' : 'حالة'"
        :subtitle="$escalation->case_type"
        :breadcrumbs="[
            ['label' => 'يحتاج قرارك', 'url' => route('volunteer.escalations')],
            ['label' => 'تفاصيل الحالة'],
        ]" />

    <section class="card p-4 space-y-2 text-sm">
        <p>المستوى الحاليّ: {{ $escalation->level }} — {{ $escalation->is_top_level ? 'السقف (48 ساعة)' : 'نافذة 24 ساعة' }}</p>
        <p>تنتهي النافذة: {{ $escalation->window_due_at?->format('Y-m-d H:i') }}</p>
        <p>التسوية الآليّة إن فاتت نافذة السقف: <strong>{{ $settlement }}</strong></p>

        @if ($subject?->title)
            <p>الموضوع: {{ $subject->title }}</p>
        @endif

        @if (! empty($payload['note']))
            <p class="rounded-xl p-3" style="background: var(--surface-sunken)">{{ $payload['note'] }}</p>
        @endif
    </section>
@endsection
