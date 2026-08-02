@php
    /** بوب-أبات الاجتماع الواحد: تسجيل حضور · اعتذار مسبق · إنهاء (24.4) */
    $status = $mine[$meeting->id]->status ?? null;
    $canManage = $scope->canManage(auth()->user(), $meeting);
    $windowOpen = $attendance->windowOpen($meeting);
@endphp

@if ($windowOpen && $status !== 'registered')
    <x-modal :id="'register-'.$meeting->id" title="تسجيل حضور — {{ $meeting->title }}">
        <form method="post" action="{{ route('volunteer.meetings.register', $meeting) }}" class="space-y-3">
            @csrf
            <p class="text-sm" style="color: var(--text-muted)">
                التحقّق بيتمّ على الخادم. الكود الغلط مش هياخد منك محاولتك — جرّب تاني براحتك.
            </p>

            @if ($meeting->attendance_code)
                <label class="block text-sm">
                    <span class="block text-xs mb-1" style="color: var(--text-muted)">كود الحضور / OTP</span>
                    <input type="text" name="code" required inputmode="latin" autocomplete="off"
                           class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                </label>
            @endif

            @foreach ($meeting->questions as $question)
                <fieldset class="rounded-xl p-3" style="border: 1px solid var(--border)">
                    <legend class="text-sm px-1">{{ $question->prompt }}</legend>
                    @foreach ((array) $question->options as $option)
                        <label class="flex items-center gap-2 text-sm py-1">
                            <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option }}"
                                   style="accent-color: var(--color-brand-500)">
                            {{ $option }}
                        </label>
                    @endforeach
                </fieldset>
            @endforeach

            <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">أكّد تسجيل حضوري</button>
        </form>
    </x-modal>
@endif

@if ($meeting->status !== 'ended' && ! in_array($status, ['excused', 'excused_settled'], true))
    <x-modal :id="'excuse-'.$meeting->id" title="اعتذار مسبق — {{ $meeting->title }}">
        <form method="post" action="{{ route('volunteer.meetings.excuse', $meeting) }}" class="space-y-3">
            @csrf
            <p class="text-sm" style="color: var(--text-muted)">الاعتذار المسبق بيمنع خصم الغياب تمامًا.</p>
            <label class="block text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">سبب مختصر</span>
                <textarea name="reason" rows="3" required maxlength="500"
                          class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>
            <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">أرسل الاعتذار</button>
        </form>
    </x-modal>
@endif

@if ($canManage && $meeting->status !== 'ended')
    <x-modal :id="'end-'.$meeting->id" title="إنهاء الاجتماع — {{ $meeting->title }}">
        <form method="post" action="{{ route('volunteer.meetings.end', $meeting) }}"
              enctype="multipart/form-data" class="space-y-3">
            @csrf
            <label class="block text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">عدد ساعات نافذة التسجيل</span>
                <input type="number" name="window_hours" min="1" max="{{ $attendance->maxWindowHours() }}"
                       value="{{ $attendance->defaultWindowHours() }}" required
                       class="w-full rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
            </label>
            <label class="block text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">المحضر</span>
                <textarea name="minutes" rows="5"
                          class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"></textarea>
            </label>
            <label class="block text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">مرفقات (اختياريّ)</span>
                <input type="file" name="attachments[]" multiple class="w-full text-sm">
            </label>
            <p class="text-xs" style="color: var(--text-muted)">
                إنهاؤه بمحضر موثَّق بيدّي صاحبه {{ $attendance->valueLabel(rep_rule('meeting.managed')) }} على درجة الالتزام.
            </p>
            <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">إنهاء وفتح النافذة</button>
        </form>
    </x-modal>
@endif
