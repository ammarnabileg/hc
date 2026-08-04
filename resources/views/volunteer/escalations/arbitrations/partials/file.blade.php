@php
    /**
     * ملفّ الحالة أمام المحكّم (23 — القسم 5):
     * شكل المخرجات · الاعتراض · الردّ · المستلَم · سلسلة الرسائل ·
     * تواصل بلا كشف الرقم · وقرار نهائيّ بمبرّر إجباريّ.
     */
    $case = $file['arbitration'];
@endphp

<article class="card p-4 space-y-4">
    <header class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="font-bold">{{ setting('volunteer.escalations_arbitrations_file.heading', 'قضيّة #') }}{{ $case->id }}</h2>
            <p class="text-xs mt-1" style="color: var(--text-muted)">
                {{ setting('volunteer.escalations_arbitrations_file.text', 'المهمّة:') }} {{ $file['task']?->title ?? '—' }}
                @if ($file['contribution']) · {{ setting('volunteer.escalations_arbitrations_file.text_2', 'البند:') }} {{ $file['contribution']->item_title }} @endif
            </p>
        </div>
        <x-state-badge :state="$case->status === 'decided' ? 'ok' : $file['state']"
                       :label="$case->status === 'decided' ? setting('volunteer.escalations_arbitrations_file.label', 'نهائيّ') : setting('volunteer.escalations_arbitrations_file.label_2', 'نافذة: ').$case->window_due_at?->format('Y-m-d H:i')" />
    </header>

    @if ($file['skipReason'])
        <p class="rounded-xl px-3 py-2 text-sm"
           style="background: color-mix(in srgb, var(--color-state-warn) 12%, transparent)">
            ▲ {{ $file['skipReason'] }}
        </p>
    @endif

    <section>
        <h3 class="text-sm font-bold mb-1">{{ setting('volunteer.escalations_arbitrations_file.heading_2', 'شكل المخرجات المطلوب') }}</h3>
        <p class="text-sm whitespace-pre-line rounded-xl p-3" style="background: var(--surface-sunken)">
            {{ $file['spec'] ?: setting('volunteer.escalations_arbitrations_file.text_3', 'لم يُحدَّد.') }}
        </p>
    </section>

    <section>
        <h3 class="text-sm font-bold mb-1">{{ setting('volunteer.escalations_arbitrations_file.heading_3', 'الاعتراض الأوّل') }}</h3>
        <p class="text-sm whitespace-pre-line">{{ $file['claim'] }}</p>
    </section>

    <section>
        <h3 class="text-sm font-bold mb-2">{{ setting('volunteer.escalations_arbitrations_file.heading_4', 'الأطراف والتواصل') }}</h3>
        <div class="space-y-2">
            @foreach ($file['parties'] as $party)
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm py-2"
                     style="border-top: 1px solid var(--border)">
                    <span>{{ $party['user']->name }} <span style="color: var(--text-muted)">#{{ $party['user']->code }}</span></span>
                    <span class="flex items-center gap-2">
                        <span dir="ltr">{{ $party['contact']['value'] }}</span>
                        <x-state-badge :state="$party['contact']['masked'] ? 'idle' : 'ok'"
                                       :label="$party['contact']['masked'] ? setting('volunteer.escalations_arbitrations_file.label_3', 'مقنَّع') : setting('volunteer.escalations_arbitrations_file.label_4', 'ظاهر')" />
                    </span>
                    <span class="text-xs w-full" style="color: var(--text-muted)">{{ $party['contact']['hint'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section>
        <h3 class="text-sm font-bold mb-2">{{ setting('volunteer.escalations_arbitrations_file.heading_5', 'سلسلة الرسائل') }}</h3>
        @forelse ($file['messages'] as $message)
            <div class="text-sm py-2" style="border-top: 1px solid var(--border)">
                <span class="text-xs" style="color: var(--text-muted)">
                    {{ $file['senders'][$message->user_id]->name ?? '—' }} · {{ $message->created_at?->format('Y-m-d H:i') }}
                </span>
                <p class="mt-1 whitespace-pre-line">{{ $message->body }}</p>
            </div>
        @empty
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('volunteer.escalations_arbitrations_file.text_4', 'لسّه مفيش رسائل.') }}</p>
        @endforelse

        @if ($case->status === 'open')
            <form method="post" action="{{ route('volunteer.arbitrations.message', $case) }}" class="mt-3 flex gap-2">
                @csrf
                <input type="text" name="body" required placeholder="{{ setting('volunteer.escalations_arbitrations_file.placeholder', 'اكتب ردّك…') }}"
                       class="flex-1 rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.escalations_arbitrations_file.action', 'إرسال') }}</button>
            </form>
        @else
            <p class="text-xs mt-3" style="color: var(--text-muted)">{{ setting('volunteer.escalations_arbitrations_file.text_5', 'الرسائل مقفولة على هذه القضيّة.') }}</p>
        @endif
    </section>

    @if ($file['isArbiter'] && $case->status !== 'decided')
        <section class="pt-3" style="border-top: 1px solid var(--border)">
            <div class="flex items-center justify-between gap-2 mb-3">
                <h3 class="text-sm font-bold">{{ setting('volunteer.escalations_arbitrations_file.heading_6', 'قرار المحكّم') }}</h3>
                <form method="post" action="{{ route('volunteer.arbitrations.lock', $case) }}">
                    @csrf
                    <button type="submit" class="rounded-xl px-3 py-1.5 text-xs"
                            style="border: 1px solid var(--border)">{{ setting('volunteer.escalations_arbitrations_file.action_2', 'قفل الرسائل') }}</button>
                </form>
            </div>

            <form method="post" action="{{ route('volunteer.arbitrations.decide', $case) }}" class="space-y-3">
                @csrf

                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('volunteer.escalations_arbitrations_file.field', 'نوع القرار') }}</span>
                    <select name="decision_type" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="award">(+) VXP {{ setting('volunteer.escalations_arbitrations_file.option', 'لطرف أو للطرفين') }}</option>
                        <option value="deduct">(−) VXP {{ setting('volunteer.escalations_arbitrations_file.option', 'لطرف أو للطرفين') }}</option>
                        <option value="split">{{ str_replace(':share', $file['settlementShare'], (string) setting('volunteer.escalations_arbitrations_file.option_2', 'قيمة وسط (:share VXP لكلٍّ)')) }}</option>
                        <option value="shelved">{{ setting('volunteer.escalations_arbitrations_file.option_4', 'حفظ القضيّة — لا شيء يتمّ ولا يمسّ Rep') }}</option>
                    </select>
                </label>

                <div class="grid grid-cols-2 gap-3">
                    <x-form.input name="owner_amount" :label="setting('volunteer.escalations_arbitrations_file.label_5', 'قيمة المالك (VXP)')" type="number" step="0.25" value="0" />
                    <x-form.input name="contributor_amount" :label="setting('volunteer.escalations_arbitrations_file.label_6', 'قيمة المساهم (VXP)')" type="number" step="0.25" value="0" />
                </div>

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('volunteer.escalations_arbitrations_file.field_2', 'المبرّر') }} <span style="color: var(--color-state-danger)">*</span></span>
                    <textarea name="decision_justification" rows="3" required
                              class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    @error('decision_justification')
                        <span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>
                    @enderror
                </label>

                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="confirm_final" value="1" required style="accent-color: var(--color-brand-500)">
                    <span>{{ setting('volunteer.escalations_arbitrations_file.field_3', 'أُقِرّ بأنّ') }} <strong>{{ setting('volunteer.escalations_arbitrations_file.strong', 'القرار نهائيّ ولا يُعاد') }}</strong>، {{ setting('volunteer.escalations_arbitrations_file.field_4', 'وقد راجعت معاينة الأثر أعلاه.') }}</span>
                </label>

                <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.escalations_arbitrations_file.action_3', 'تسجيل القرار النهائيّ') }}</button>
            </form>
        </section>
    @elseif ($case->status === 'decided')
        <section class="pt-3 text-sm" style="border-top: 1px solid var(--border)">
            <x-state-badge state="ok" :label="setting('volunteer.escalations_arbitrations_file.label', 'نهائيّ')" />
            <p class="mt-2">{{ setting('volunteer.escalations_arbitrations_file.field_5', 'النوع:') }} {{ $case->decision_type }} · {{ setting('volunteer.escalations_arbitrations_file.field_6', 'المالك:') }} {{ (float) $case->owner_amount }} VXP ·
                {{ setting('volunteer.escalations_arbitrations_file.field_7', 'المساهم:') }} {{ (float) $case->contributor_amount }} VXP</p>
            <p class="mt-1 whitespace-pre-line">{{ $case->decision_justification }}</p>
        </section>
    @endif
</article>
