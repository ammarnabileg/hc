@php
    /**
     * ويدجت الدعوات داخل السايد بار (الدستور 13-ج): عدد المدعوّين + رابط الدعوة
     * + زرّ «نسخ الرابط» + زرّ «دعوة أصدقائك».
     *
     * كان السايد بار يحمل روابط تنقّل فقط، والبند ينصّ على الويدجت نفسه لا على
     * رابطٍ لصفحته. والرابط والعدّاد يُقرآن من `ReferralService` — نفس المصدر الذي
     * تقرأ منه صفحة الدعوات، فلا يفترق رقمان لمعنًى واحد.
     */
    $referrals = app(\App\Services\Referral\ReferralService::class);
    $inviteLink = $referrals->link($u);
    $invitedCount = \App\Models\Referral::query()
        ->where('referrer_id', $u->id)
        ->whereNotNull('referred_id')
        ->count();
@endphp

<section class="card p-3 space-y-2" aria-label="دعوة الأصدقاء">
    <div class="flex items-center justify-between gap-2">
        <span class="text-sm font-semibold">👥 ادعُ أصدقاءك</span>
        <span class="text-xs rounded-full px-2 py-0.5"
              style="background: var(--surface-sunken); color: var(--text-muted)">{{ $invitedCount }} مدعوّ</span>
    </div>

    <label class="sr-only" for="sb-invite-link">رابط دعوتك</label>
    <input id="sb-invite-link" type="text" readonly value="{{ $inviteLink }}"
           data-invite-link
           class="w-full rounded-xl px-2 py-2 text-[11px] font-mono truncate"
           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text-muted)">

    <div class="flex items-center gap-2">
        <button type="button" data-copy-invite
                class="flex-1 rounded-xl px-2 py-2 text-xs font-semibold motion-standard"
                style="min-height: 44px; background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
            نسخ الرابط
        </button>

        @if (\Illuminate\Support\Facades\Route::has('referral.index'))
            <a href="{{ route('referral.index') }}"
               class="flex-1 rounded-xl px-2 py-2 text-xs font-semibold text-center motion-standard flex items-center justify-center"
               style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                دعوة أصدقائك
            </a>
        @endif
    </div>
</section>

<script>
    /* ردّ فوريّ على النسخ — «اتنسخ ✓» في الزرّ نفسه (2.17-أ) */
    (function () {
        var button = document.querySelector('[data-copy-invite]');
        var field = document.querySelector('[data-invite-link]');

        if (!button || !field) return;

        button.addEventListener('click', function () {
            var done = function () {
                var original = button.textContent;
                button.textContent = 'اتنسخ ✓';
                setTimeout(function () { button.textContent = original; }, 1800);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(field.value).then(done, done);
                return;
            }

            /* بلا واجهة الحافظة: التحديد يبقى بابًا يدويًّا مفهومًا لا رسالة خطأ */
            field.removeAttribute('readonly');
            field.select();
            try { document.execCommand('copy'); } catch (e) { /* المتصفّح رفض — التحديد قائم */ }
            field.setAttribute('readonly', 'readonly');
            done();
        });
    })();
</script>
