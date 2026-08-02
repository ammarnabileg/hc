@php
    /**
     * كارت المرشّح (13.4-د · 24.4-12).
     * الخصوصيّة طبقتان: **الرقم لفريق التوظيف**، و**سبب الخروج للمخوَّلين وحدهم**
     * — وما لا يملكه المستخدم **يُخفى فعلًا** لا يُعطَّل (2.15-أ-7).
     */
    $waitingDays = $pipeline->waitingDays($candidate);
    $returningLine = $pipeline->returningLine($candidate);
    $courseScores = collect($candidate->course_scores ?? []);
@endphp

<article class="card p-3 animate-fadeup" data-candidate="{{ $candidate->id }}" data-stage="{{ $candidate->stage }}"
         data-name="{{ $candidate->user?->name }}" data-move-url="{{ route('volunteer.recruitment.move', $candidate) }}"
         @if ($canMove) draggable="true" @endif>

    <div class="flex items-start gap-2">
        <x-avatar :user="$candidate->user" size="9" />
        <div class="min-w-0 flex-1">
            <h3 class="text-sm font-bold truncate">{{ $candidate->user?->name ?? 'مرشّح' }}</h3>
            <p class="text-xs" style="color: var(--text-muted)">#{{ $candidate->user?->code }}</p>
        </div>
        <x-state-badge :state="$pipeline->waitingState($candidate)" :label="'انتظار '.$waitingDays.' يومًا'" />
    </div>

    {{-- ⭐ شارة «عائد» + سطر الخدمة السابقة (13.4-ق-و) --}}
    @if ($returningLine)
        <div class="mt-2 rounded-xl px-2 py-1.5 text-xs"
             style="background: color-mix(in srgb, var(--color-state-honor) 12%, transparent); color: var(--color-state-honor)">
            <span aria-hidden="true">★</span> عائد — {{ $returningLine }}
        </div>
        {{-- سبب الخروج للمخوَّلين وحدهم، ولا يُنشَر لفريق التوظيف (13.4-ق-هـ) --}}
        @if ($canSeeExitReason && $candidate->previous_exit_type)
            <p class="mt-1 text-xs" style="color: var(--text-muted)">سبب الخروج: {{ $candidate->previous_exit_type }}</p>
        @endif
    @endif

    <dl class="mt-2 grid grid-cols-2 gap-1 text-xs">
        <dt style="color: var(--text-muted)">شهادة التأهيليّ</dt>
        <dd class="font-semibold text-end">{{ $candidate->qualifying_score ?? '—' }}</dd>

        @foreach ($courseScores->take(3) as $courseName => $score)
            <dt class="truncate" style="color: var(--text-muted)">{{ $courseName }}</dt>
            <dd class="text-end">{{ $score }}</dd>
        @endforeach
    </dl>

    @if ($candidate->relationLoaded('fits') && $candidate->fits->isNotEmpty())
        <div class="mt-2 flex flex-wrap gap-1">
            @foreach ($candidate->fits as $entity)
                <span class="text-xs rounded-full px-2 py-0.5" style="background: var(--surface-sunken)">{{ $entity->name_ar }}</span>
            @endforeach
        </div>
    @endif

    @if ($candidate->cv_summary)
        <p class="mt-2 text-xs line-clamp-3" style="color: var(--text-muted)">{{ $candidate->cv_summary }}</p>
    @endif

    {{-- ⭐ رقم المرشّح لفريق التوظيف فقط (13.4-د — حوكمة) --}}
    @if ($canSeePhone && $candidate->user?->phone)
        <p class="mt-2 text-xs" style="color: var(--text-muted)">
            <span aria-hidden="true">☎</span>
            <a href="https://wa.me/{{ preg_replace('/\D/', '', $candidate->user->phone) }}" class="hover:underline"
               dir="ltr">{{ $candidate->user->phone }}</a>
        </p>
    @endif

    @if ($canSeePhone)
        {{-- التفاصيل في بوب-أب لا صفحة جديدة (2.15-أ-6) --}}
        <button type="button" class="btn mt-3 w-full rounded-xl px-3 py-2 text-xs"
                style="background: var(--surface-sunken)"
                data-detail-url="{{ route('volunteer.recruitment.show', $candidate) }}">التفاصيل</button>
    @endif

    @if ($canMove)
        {{-- الموبايل: قائمة بدل السحب — والتأكيد بسبب هو هو (2.15-ج) --}}
        <label class="mt-3 block md:hidden text-xs">
            <span class="block mb-1" style="color: var(--text-muted)">انقل إلى</span>
            <select data-move-select class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">اختر مرحلة…</option>
                @foreach ($pipeline->stages() as $key => $label)
                    @if ($key !== $candidate->stage)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endif
                @endforeach
            </select>
        </label>
    @endif
</article>
