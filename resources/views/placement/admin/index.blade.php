@extends('layouts.admin')

@section('title', setting('onboarding.placement.admin.title'))

@section('content')
    {{--
        شاشة «الاختبار التمهيديّ» (24 — محتوى الـOnboarding · 2.5-د-2 · 12).
        الجدول بأعمدته المنصوصة حرفيًّا: السؤال · النوع · مكافأة XP · مكافأة تذاكر
        · الترتيب (سحب) · الحالة · إجراءات.
    --}}
    <x-page-header :title="setting('onboarding.placement.admin.title')"
                   :subtitle="setting('onboarding.placement.admin.subtitle')">
        <x-slot:action>
            @can('placement_test.create')
                <button type="button" data-modal-open="placement-question" data-question-new
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('onboarding.placement.admin.add_cta') }}
                </button>
            @endcan

            @can('placement_test.export')
                <a href="{{ route('admin.placement-test.export') }}"
                   class="rounded-xl px-4 py-2 text-sm"
                   style="background: var(--surface-sunken)">{{ setting('onboarding.placement.admin.export_cta') }}</a>
            @endcan
        </x-slot:action>
    </x-page-header>

    @if ($questions->isEmpty())
        {{-- الحالة الفارغة المنصوصة: سطر واحد + زرّ واحد (24) --}}
        <x-empty :message="setting('onboarding.placement.admin.empty')" />
    @else
        {{-- ديسكتوب: جدول بأعمدته السبعة --}}
        <div class="card overflow-x-auto hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border)">
                        <th class="p-3 text-start">{{ setting('onboarding.placement.admin.col_prompt') }}</th>
                        <th class="p-3 text-start">{{ setting('onboarding.placement.admin.col_kind') }}</th>
                        <th class="p-3 text-start">{{ setting('onboarding.placement.admin.col_xp') }}</th>
                        <th class="p-3 text-start">{{ setting('onboarding.placement.admin.col_tickets') }}</th>
                        <th class="p-3 text-start">{{ setting('onboarding.placement.admin.col_order') }}</th>
                        <th class="p-3 text-start">{{ setting('onboarding.placement.admin.col_status') }}</th>
                        <th class="p-3 text-end">{{ setting('onboarding.placement.admin.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody data-sortable="{{ route('admin.placement-test.reorder') }}">
                    @foreach ($questions as $question)
                        <tr data-sort-id="{{ $question->id }}" style="border-bottom: 1px solid var(--border)">
                            <td class="p-3">
                                <div class="max-w-md truncate">{{ $question->prompt }}</div>
                                <div class="text-xs mt-1" style="color: var(--text-muted)">
                                    {{ $answerTypes[$question->type] ?? $question->type }}
                                    · {{ setting('onboarding.placement.admin.answers_count') }}
                                    {{ $answerCounts[$question->id] ?? 0 }}
                                </div>
                            </td>
                            <td class="p-3">{{ $mediaKinds[$question->media_kind] ?? $question->media_kind }}</td>
                            <td class="p-3">{{ (int) $question->reward_xp }}</td>
                            <td class="p-3">{{ (int) $question->reward_tickets }}</td>
                            <td class="p-3">
                                <span class="cursor-grab select-none" aria-hidden="true">⠿</span>
                                <span class="text-xs" style="color: var(--text-muted)">{{ (int) $question->sort_order }}</span>
                            </td>
                            <td class="p-3">
                                <x-state-badge :state="$question->is_active ? 'ok' : 'idle'"
                                               :label="$question->is_active
                                                   ? setting('onboarding.placement.admin.state_active')
                                                   : setting('onboarding.placement.admin.state_paused')" />
                            </td>
                            <td class="p-3 text-end">
                                @include('placement.admin.partials.row-actions', ['question' => $question])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- موبايل: كروت رأسيّة بلا تمرير أفقيّ، والترتيب بأزرار فوق/تحت (2.15-ج) --}}
        <div class="md:hidden space-y-3" data-sortable="{{ route('admin.placement-test.reorder') }}">
            @foreach ($questions as $question)
                <div class="card p-4" data-sort-id="{{ $question->id }}">
                    <div class="flex items-start gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold">{{ $question->prompt }}</div>
                            <div class="text-xs mt-1" style="color: var(--text-muted)">
                                {{ $mediaKinds[$question->media_kind] ?? $question->media_kind }}
                                · {{ setting('onboarding.placement.admin.col_xp') }} {{ (int) $question->reward_xp }}
                                · {{ setting('onboarding.placement.admin.col_tickets') }} {{ (int) $question->reward_tickets }}
                            </div>
                        </div>
                        <x-state-badge :state="$question->is_active ? 'ok' : 'idle'"
                                       :label="$question->is_active
                                           ? setting('onboarding.placement.admin.state_active')
                                           : setting('onboarding.placement.admin.state_paused')" />
                    </div>

                    <div class="flex items-center gap-2 mt-3 flex-wrap">
                        <button type="button" data-sort-up class="rounded-lg px-2 py-1 text-xs"
                                style="background: var(--surface-sunken)"
                                aria-label="{{ setting('onboarding.placement.admin.move_up') }}">▲</button>
                        <button type="button" data-sort-down class="rounded-lg px-2 py-1 text-xs"
                                style="background: var(--surface-sunken)"
                                aria-label="{{ setting('onboarding.placement.admin.move_down') }}">▼</button>
                        @include('placement.admin.partials.row-actions', ['question' => $question])
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- بوب-أب [سؤال]: النصّ + النوع + الوسائط + الخيارات + الإجابة + XP + تذاكر (24) --}}
    @canany(['placement_test.create', 'placement_test.edit'])
        <x-modal id="placement-question" :title="setting('onboarding.placement.admin.form_title')">
            <form method="post" action="{{ route('admin.placement-test.store') }}" class="space-y-3" data-question-form>
                @csrf

                <x-form.input name="prompt" :label="setting('onboarding.placement.admin.field_prompt')" required />

                <div class="grid md:grid-cols-2 gap-3">
                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('onboarding.placement.admin.field_kind') }}</span>
                        <select name="media_kind" data-question-kind class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($mediaKinds as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('onboarding.placement.admin.field_type') }}</span>
                        <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($answerTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <x-form.input name="media_url" :label="setting('onboarding.placement.admin.field_media_url')"
                              :hint="setting('onboarding.placement.admin.field_media_hint')" />

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('onboarding.placement.admin.field_embed') }}</span>
                    <textarea name="embed_html" rows="3" class="w-full rounded-xl px-3 py-2 text-sm font-mono"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    <span class="block text-xs mt-1" style="color: var(--text-muted)">
                        {{ setting('onboarding.placement.admin.field_embed_hint') }}
                    </span>
                    @error('embed_html')
                        <span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>
                    @enderror
                </label>

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('onboarding.placement.admin.field_options') }}</span>
                    <textarea name="options" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    <span class="block text-xs mt-1" style="color: var(--text-muted)">
                        {{ setting('onboarding.placement.admin.field_options_hint') }}
                    </span>
                </label>

                <x-form.input name="correct_answer" :label="setting('onboarding.placement.admin.field_correct')"
                              :hint="setting('onboarding.placement.admin.field_correct_hint')" />

                <div class="grid md:grid-cols-2 gap-3">
                    <x-form.input name="reward_xp" type="number" :label="setting('onboarding.placement.admin.field_xp')" value="0" />
                    <x-form.input name="reward_tickets" type="number" :label="setting('onboarding.placement.admin.field_tickets')" value="0" />
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked>
                    {{ setting('onboarding.placement.admin.field_active') }}
                </label>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('onboarding.placement.admin.save_cta') }}
                </button>
            </form>
        </x-modal>
    @endcanany

    @include('admin.courses.partials.sortable')
    @include('admin.courses.partials.toast')

    @push('scripts')
        <script>
            /* تعبئة الفورم من زرّ التعديل — نفس البوب-أب لا شاشة ثانية (2.15-أ-6) */
            (function () {
                const form = document.querySelector('[data-question-form]');
                if (!form) return;

                const storeUrl = @json(route('admin.placement-test.store'));

                document.addEventListener('click', (e) => {
                    if (e.target.closest('[data-question-new]')) {
                        form.reset();
                        form.action = storeUrl;
                        form.querySelector('[name=_method]')?.remove();
                        return;
                    }

                    const btn = e.target.closest('[data-question-edit]');
                    if (!btn) return;

                    const data = JSON.parse(btn.dataset.questionEdit);
                    form.action = btn.dataset.questionUrl;

                    let method = form.querySelector('[name=_method]');
                    if (!method) {
                        method = document.createElement('input');
                        method.type = 'hidden';
                        method.name = '_method';
                        form.appendChild(method);
                    }
                    method.value = 'PUT';

                    form.querySelector('[name=prompt]').value = data.prompt ?? '';
                    form.querySelector('[name=media_kind]').value = data.media_kind ?? 'none';
                    form.querySelector('[name=type]').value = data.type ?? 'choice';
                    form.querySelector('[name=media_url]').value = data.media_url ?? '';
                    form.querySelector('[name=embed_html]').value = data.embed_html ?? '';
                    form.querySelector('[name=options]').value = (data.options ?? []).join('\n');
                    form.querySelector('[name=correct_answer]').value = data.correct_answer ?? '';
                    form.querySelector('[name=reward_xp]').value = data.reward_xp ?? 0;
                    form.querySelector('[name=reward_tickets]').value = data.reward_tickets ?? 0;
                    form.querySelector('[name=is_active][type=checkbox]').checked = !!data.is_active;
                });
            })();
        </script>
    @endpush
@endsection
