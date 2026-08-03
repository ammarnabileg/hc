{{--
    إجراءات صفّ السؤال — **بالصلاحيّة لا بالتعطيل** (2.15-أ-7): مَن لا يملك
    الفعل لا يرى زرّه أصلًا. وكلّ فعلٍ من مورده في 12.2.2: `placement_test.edit`
    · `placement_test.delete`.
--}}
@php
    // حمولة التعديل تُبنى هنا لا داخل الوسم — فسطرٌ واحد يقرؤه Blade بلا لبس
    $payload = [
        'prompt' => $question->prompt,
        'media_kind' => $question->media_kind,
        'media_url' => $question->media_url,
        'embed_html' => $question->embed_html,
        'type' => $question->type,
        'options' => $question->publicOptions(),
        'correct_answer' => $question->correct_answer,
        'reward_xp' => (int) $question->reward_xp,
        'reward_tickets' => (int) $question->reward_tickets,
        'is_active' => (bool) $question->is_active,
    ];
@endphp

<div class="inline-flex items-center gap-2 flex-wrap">
    @can('placement_test.edit')
        <button type="button" data-modal-open="placement-question"
                data-question-edit="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}"
                data-question-url="{{ route('admin.placement-test.update', $question) }}"
                class="rounded-lg px-2 py-1 text-xs" style="background: var(--surface-sunken)">
            {{ setting('onboarding.placement.admin.edit_cta') }}
        </button>

        <form method="post" action="{{ route('admin.placement-test.toggle', $question) }}">
            @csrf
            <button type="submit" class="rounded-lg px-2 py-1 text-xs" style="background: var(--surface-sunken)">
                {{ $question->is_active
                    ? setting('onboarding.placement.admin.pause_cta')
                    : setting('onboarding.placement.admin.resume_cta') }}
            </button>
        </form>
    @endcan

    @can('placement_test.delete')
        <form method="post" action="{{ route('admin.placement-test.destroy', $question) }}"
              onsubmit="return confirm('{{ setting('onboarding.placement.admin.delete_confirm') }}')">
            @csrf @method('delete')
            <button type="submit" class="rounded-lg px-2 py-1 text-xs" style="background: var(--surface-sunken)">
                {{ setting('onboarding.placement.admin.delete_cta') }}
            </button>
        </form>
    @endcan
</div>
