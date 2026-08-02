@php
    /**
     * تاب «ملاحظات إداريّة» (13.4-م-5): **للمخوَّل فقط · سرّيّة · بـAudit كامل**.
     * ولا يصل إليه صاحب البروفايل أصلًا — التاب نفسه لا يظهر له (2.15-أ-7).
     */
    $n = $panel;
@endphp

<div class="card p-4 mb-3 flex items-start gap-3">
    <x-state-badge state="warn" label="سرّيّة" />
    <p class="text-sm">الملاحظات دي بتتسجّل باسمك وبتاريخها في سجلّ التدقيق — واستخدامها لقرار، مش لتصفية حساب.</p>
</div>

@if ($n['can_write'])
    <section class="card p-4 mb-3">
        <form method="POST" action="{{ route('volunteer.profile.notes.store', ['code' => $owner->code]) }}" class="space-y-3">
            @csrf
            <textarea name="body" rows="3" required
                      minlength="{{ (int) setting('volunteer.profile.notes.min_chars', 5) }}"
                      maxlength="{{ (int) setting('volunteer.profile.notes.max_chars', 2000) }}"
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                      placeholder="اكتب ملاحظة مفيدة وقت القرار…"></textarea>
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="min-height: 44px; background: var(--color-brand-500); color: #04201c">احفظ الملاحظة</button>
        </form>
    </section>
@endif

<section class="card p-4">
    <h2 class="font-bold text-sm mb-3">الملاحظات</h2>
    @if ($n['notes']->isEmpty())
        <p class="text-sm" style="color: var(--text-muted)">مفيش ملاحظات لحدّ دلوقتي.</p>
    @else
        <ul class="space-y-3">
            @foreach ($n['notes'] as $note)
                <li>
                    <p class="text-sm">{{ $note->body }}</p>
                    <p class="text-xs" style="color: var(--text-muted)">
                        {{ $note->author_name ?? 'غير معروف' }} · {{ \Illuminate\Support\Carbon::parse($note->created_at)->diffForHumans() }}
                    </p>
                </li>
            @endforeach
        </ul>
    @endif
</section>

{{-- المستوى الرابع (الأدمن) يرى سجلّ التدقيق كذلك (13.4-م) --}}
@if ($n['audit']->isNotEmpty())
    <section class="card p-4 mt-3">
        <h2 class="font-bold text-sm mb-3">سجلّ التدقيق على الملاحظات</h2>
        <ul class="space-y-2 text-sm">
            @foreach ($n['audit'] as $row)
                <li class="flex items-center justify-between gap-2">
                    <span class="min-w-0 truncate">{{ $row->action }}</span>
                    <span class="text-xs" style="color: var(--text-muted)">
                        {{ $row->user?->shortName() ?? 'النظام' }} · {{ $row->created_at->diffForHumans() }}
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
