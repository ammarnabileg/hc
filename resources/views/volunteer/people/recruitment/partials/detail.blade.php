@php
    /** بانل تفاصيل المرشّح — يُحمَّل داخل بوب-أب فلا يفقد المستخدم مكانه (2.15-أ-6). */
    $line = $pipeline->returningLine($candidate);
@endphp

<div class="space-y-4">
    <header class="flex items-start gap-3">
        <x-avatar :user="$candidate->user" size="12" />
        <div class="min-w-0">
            <h2 class="font-bold">{{ $candidate->user?->name }}</h2>
            <p class="text-xs" style="color: var(--text-muted)">#{{ $candidate->user?->code }}</p>
        </div>
    </header>

    @if ($line)
        <p class="rounded-xl px-3 py-2 text-sm"
           style="background: color-mix(in srgb, var(--color-state-honor) 12%, transparent); color: var(--color-state-honor)">
            <span aria-hidden="true">★</span> {{ setting('volunteer.people_recruitment_detail.text', 'عائد —') }} {{ $line }}
        </p>
        @if ($canSeeExitReason && $candidate->previous_exit_type)
            <p class="text-xs" style="color: var(--text-muted)">{{ setting('volunteer.people_recruitment_detail.text_2', 'سبب الخروج:') }} {{ $candidate->previous_exit_type }}</p>
        @endif
    @endif

    <dl class="grid grid-cols-2 gap-2 text-sm">
        <dt style="color: var(--text-muted)">{{ setting('volunteer.people_recruitment_detail.text_3', 'درجة شهادة التأهيليّ') }}</dt>
        <dd class="font-semibold">{{ $candidate->qualifying_score ?? '—' }}</dd>
        <dt style="color: var(--text-muted)">{{ setting('volunteer.people_recruitment_detail.text_4', 'مدّة الانتظار') }}</dt>
        <dd>{{ $pipeline->waitingDays($candidate) }} {{ setting('volunteer.common.days', 'يومًا') }}</dd>
    </dl>

    @if ($fits->isNotEmpty())
        <div>
            <h3 class="text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.people_recruitment_detail.heading', 'الأقسام المناسبة') }}</h3>
            <div class="flex flex-wrap gap-1">
                @foreach ($fits as $entity)
                    <span class="text-xs rounded-full px-2 py-0.5" style="background: var(--surface-sunken)">{{ $entity->name_ar }}</span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- تايم-لاين «ملخّص» — محطّات الرحلة بتواريخها --}}
    <ol class="space-y-2 text-sm">
        <li class="flex items-center gap-2">
            <span aria-hidden="true">●</span> {{ setting('volunteer.people_recruitment_detail.bullet', 'بدأ التأهيليّ') }}
            <span class="text-xs" style="color: var(--text-muted)">{{ $candidate->applied_at?->translatedFormat('j F Y') ?? '—' }}</span>
        </li>
        <li class="flex items-center gap-2">
            <span aria-hidden="true">●</span> {{ setting('volunteer.people_recruitment_detail.bullet_2', 'آخر تغيير مرحلة') }}
            <span class="text-xs" style="color: var(--text-muted)">{{ $candidate->stage_changed_at?->translatedFormat('j F Y') ?? '—' }}</span>
        </li>
    </ol>

    @if ($canSeePhone && $candidate->user?->phone)
        <a class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold"
           style="background: var(--color-brand-500); color: #04201c"
           href="https://wa.me/{{ preg_replace('/\D/', '', $candidate->user->phone) }}?text={{ rawurlencode($whatsapp) }}">
            {{ setting('volunteer.people_recruitment_detail.link', 'واتساب بقالب جاهز') }}
        </a>
    @endif
</div>
