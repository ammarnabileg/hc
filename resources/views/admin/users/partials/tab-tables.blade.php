{{--
    تاب «الجداول» (12.1): المعاملات · **السحوبات** · الدعوات — **كلّها بفلتر من فترة لفترة**.

    كانت الجداول تعرض آخر 25 صفًّا ثابتًا بلا فلتر، وجدول السحوبات ماكانش موجودًا
    أصلًا. والدستور يقول «كلها بفلتر من فترة لفترة» — فالفلتر واحد فوق التاب كلّه،
    لا فلتر لكلّ جدول (سؤال واحد لكلّ شاشة · 2.15-أ-1).

    وعلى الموبايل الجداول **كروت رأسيّة بلا تمرير أفقيّ** (قاعدة القبول).
--}}

<x-filters :action="route('admin.users.show', $user)" :saveable="false">
    <input type="hidden" name="tab" value="tables">

    <label class="flex flex-col gap-1">
        <span class="text-xs" style="color: var(--text-muted)">من</span>
        <input type="date" name="from" value="{{ $period['from']->toDateString() }}"
               class="rounded-xl px-3 py-2 text-sm"
               style="min-block-size: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
    </label>

    <label class="flex flex-col gap-1">
        <span class="text-xs" style="color: var(--text-muted)">إلى</span>
        <input type="date" name="to" value="{{ $period['to']->toDateString() }}"
               class="rounded-xl px-3 py-2 text-sm"
               style="min-block-size: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
    </label>

    <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
            style="min-block-size: 44px; background: var(--color-brand-500); color: #04201c">فلترة</button>
</x-filters>

<div class="grid gap-4">

    {{-- 1) المعاملات — تفاصيلها الكاملة في صفحة المحفظة (12.1) --}}
    <section class="card p-4">
        <h3 class="font-bold text-sm mb-3">المعاملات</h3>
        @if ($transactions->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">مافيش معاملات في الفترة دي — وسّع المدى.</p>
        @else
            <ul class="divide-y" style="border-color: var(--border)">
                @foreach ($transactions as $transaction)
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                        <span class="flex-1 min-w-0 truncate">{{ $transaction->reason ?? $transaction->source }}</span>
                        <span class="font-semibold">{{ number_format((float) $transaction->amount, 2) }} {{ $transaction->currency?->name_ar }}</span>
                        <span class="text-xs" style="color: var(--text-muted)">{{ $transaction->created_at?->format('Y-m-d') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 2) عمليّات السحب — الجدول اللي كان ناقصًا تمامًا (12.1-الجداول-2) --}}
    <section class="card p-4">
        <h3 class="font-bold text-sm mb-3">عمليّات السحب</h3>
        @if ($withdrawals->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">مافيش طلبات سحب في الفترة دي.</p>
        @else
            <ul class="divide-y" style="border-color: var(--border)">
                @foreach ($withdrawals as $withdrawal)
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                        <span class="flex-1 min-w-0 truncate" dir="ltr">#{{ $withdrawal->number }}</span>
                        <span class="font-semibold">{{ number_format((float) $withdrawal->net_amount, 2) }}</span>
                        <x-state-badge :state="$withdrawal->state()" :label="$withdrawal->statusLabel()" />
                        <span class="text-xs" style="color: var(--text-muted)">{{ $withdrawal->created_at?->format('Y-m-d') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 3) الدعوات + **هل أخذوا هديتهم**؟ والهديّة لا تُصرَف إلّا بعد قبول الحساب --}}
    <section class="card p-4">
        <h3 class="font-bold text-sm mb-1">الدعوات</h3>
        <p class="text-xs mb-3" style="color: var(--text-muted)">
            {{ setting('admin.users.referral_gift_note', 'الهديّة بتتصرف للطرفين بعد قبول الحساب — مش وقت التسجيل.') }}
        </p>

        @if ($referrals->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">مادعاش حدّ في الفترة دي.</p>
        @else
            <ul class="divide-y" style="border-color: var(--border)">
                @foreach ($referrals as $referral)
                    @php
                        // «لسّه» على حساب تحت المراجعة ليست تأخيرًا بل قاعدة — فنقولها صراحةً
                        $accepted = $referral->referred?->status === 'active';
                        $granted = (bool) $referral->welcome_ticket_granted;
                    @endphp
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                        <span class="flex-1 min-w-0 truncate">{{ $referral->referred?->shortName() ?? 'لسّه ماسجّلش' }}</span>
                        <x-state-badge :state="$granted ? 'ok' : ($accepted ? 'warn' : 'idle')"
                                       :label="$granted ? 'خد هديته' : ($accepted ? 'مستنّي الصرف' : 'مستنّي قبول الحساب')" />
                        <span class="text-xs" style="color: var(--text-muted)">{{ $referral->created_at?->format('Y-m-d') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
