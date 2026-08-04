@php
    /**
     * إجراءات صفّ السؤال (24.1-3): تعديل · تبديل «عام» · تكرار · نقل لدرس آخر ·
     * تعطيل · إعادة استخدام في امتحان · حذف.
     *
     * كلّ إجراء **مربوط بصلاحيّته**، وما لا يملكه المستخدم لا يظهر أصلًا (2.15-أ-7).
     */
    $u = auth()->user();
    $canCreate = $u?->can('question_bank.create');
    $canEdit = $u?->can('question_bank.edit');
    $canDelete = $u?->can('question_bank.delete');
    $canGeneral = $u?->can('general_questions.edit') || $u?->can('question_bank.manage');
    $canReuse = $u?->can('course_exam.edit') || $u?->can('question_bank.manage');
@endphp

<div class="flex flex-wrap items-center gap-3 text-xs">
    @if ($canEdit)
        <button type="button" class="underline" data-question-edit
                data-action="{{ route('admin.question-bank.update', $question) }}"
                data-lesson="{{ $question->lesson_id }}"
                data-type="{{ $question->type }}"
                data-difficulty="{{ $question->difficulty }}"
                data-prompt="{{ $question->prompt }}"
                data-options="{{ is_array($question->options) ? implode('|', $question->options) : '' }}"
                data-correct="{{ $question->correct_answer }}"
                data-placeholder="{{ $question->placeholder }}"
                data-general="{{ $question->is_general ? 1 : 0 }}"
                data-active="{{ $question->is_active ? 1 : 0 }}">{{ setting('admin.question_bank.partials.row_actions.tadyl', 'تعديل') }}</button>
    @endif

    @if ($canGeneral)
        <form method="post" action="{{ route('admin.question-bank.general', $question) }}">
            @csrf
            <button type="submit" class="underline">{{ $question->is_general ? setting('admin.question_bank.partials.row_actions.ashylh_mn_alaama', 'اشيله من العامّة') : setting('admin.question_bank.partials.row_actions.khlyh_aama', 'خلّيه عامًّا') }}</button>
        </form>
    @endif

    @if ($canReuse)
        <button type="button" class="underline" data-question-reuse
                data-action="{{ route('admin.question-bank.reuse', $question) }}">{{ setting('admin.question_bank.partials.row_actions.astamlh_fy_amthan', 'استعمله في امتحان') }}</button>
    @endif

    @if ($canEdit)
        <button type="button" class="underline" data-question-move
                data-action="{{ route('admin.question-bank.move', $question) }}"
                data-lesson="{{ $question->lesson_id }}">{{ setting('admin.question_bank.partials.row_actions.nql_ldrs', 'نقل لدرس') }}</button>

        <form method="post" action="{{ route('admin.question-bank.active', $question) }}">
            @csrf
            <button type="submit" class="underline">{{ $question->is_active ? setting('admin.question_bank.partials.row_actions.atl', 'عطّل') : setting('admin.question_bank.partials.row_actions.fal', 'فعّل') }}</button>
        </form>
    @endif

    @if ($canCreate)
        <form method="post" action="{{ route('admin.question-bank.duplicate', $question) }}">
            @csrf
            <button type="submit" class="underline">{{ setting('admin.question_bank.partials.row_actions.tkrar', 'تكرار') }}</button>
        </form>
    @endif

    @if ($canDelete)
        <form method="post" action="{{ route('admin.question-bank.destroy', $question) }}"
              onsubmit="return confirm('{{ setting('admin.question_bank.partials.row_actions.thdhf_alswal_dh_nhayya_alhdhf_malwsh_rjaa', 'تحذف السؤال ده نهائيًّا؟ الحذف مالوش رجعة — التعطيل بيوقّفه بلا ما يضيع.') }}')">
            @csrf @method('delete')
            <button type="submit" class="underline" style="color: var(--color-state-danger)">{{ setting('admin.question_bank.partials.row_actions.hdhf', 'حذف') }}</button>
        </form>
    @endif
</div>
