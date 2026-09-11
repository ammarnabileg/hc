@php
    /**
     * احتفال رحلة التسجيل (2.14): ثلاثة مستويات لا رابع —
     * 1 خفيف بلا صوت · 2 كونفيتي خفيف · 3 «**شاشة احتفال كاملة**: كونفيتي غزير
     * + صوت + **رسالة تهنئة** + **زرّ مشاركة**» (2.14-أ · المستوى 3).
     *
     * وكان المبنيّ هنا كونفيتي وصوتًا وحدهما: `$celebration['message']` يصل من
     * `CelebrationService` جاهزًا ولا يُطبَع حرفًا، ولا زرّ مشاركة أصلًا — فنصفُ
     * البند غائب. والرسالة **إعدادٌ لكلّ حدث** (2.14-ج: «نصّ رسالة التهنئة لكلّ
     * حدث») ينادي صاحبَها باسمه (2.17-أ)، فحجبُها يُسقط النداء بالاسم كذلك،
     * ويترك «قبول الحساب» — وهو حدث ذروةٍ منصوصٌ في جدول 2.14-أ — بلا تهنئة.
     *
     * **وما يُشارَك** رابطُ دعوة صاحب الاحتفال من `ReferralService` — المصدر
     * القائم نفسه الذي يقرؤه ويدجت الدعوات (7.6 · 21.1) — فلا آليّة مشاركةٍ
     * ثانية تُستحدَث، ولا رابط يُبنى في القالب بيده.
     *
     * والمشاركة تمرّ بواجهة النظام `navigator.share` أوّلًا وتتراجع للحافظة —
     * نفس نمط بطاقة المتطوّع (`cards/show`)، لا نمطٌ ثالث.
     *
     * CSS-first بلا أصول جديدة ولا مكتبات (2.14-ب). **الصوت وحده** يخضع لتوجل
     * المستخدم، و«**الأنيميشن حاضر دائمًا**» (2.3 · 2.14-ب) — ولا توجّل يطفئه
     * ولا `prefers-reduced-motion` (مرفوض بالاسم في 2.3).
     *
     * والكونفيتي من مصدرٍ واحد `<x-confetti>` — عددًا ومدّةً وشدّةً من
     * `setting()` (2.13 · 2.14-ب «مصدر واحد للاحتفالات … لا نسخ متعدّدة»).
     */
    $tier = (int) ($celebration['tier'] ?? 3);
    $confetti = match ($tier) {
        3 => 'peak',
        2 => 'light',
        default => null,
    };

    $message = (string) ($celebration['message'] ?? '');
    $label = (string) ($celebration['label'] ?? '');

    // «لا تعطّل المستخدم … وتنتهي تلقائيًّا خلال ثوانٍ» (2.14-ب) — بلا رقم محروق
    $seconds = max(2, (int) setting('celebrations.auto_dismiss_seconds', 6));

    /*
     | «زرّ مشاركة» للذروة وحدها (2.14-أ · 3): المستوى 2 «كونفيتي خفيف + صوت
     | قصير» لا غير، والمستوى 1 بلا صوتٍ أصلًا — فالزرّ لا يتسرّب لما دونها.
     */
    $shareLink = $tier === 3 && auth()->check()
        ? app(\App\Services\Referral\ReferralService::class)->link(auth()->user())
        : null;

    /** نصوص السكربت — من الإعدادات لا محروقةً في الجافاسكربت (2.13-أ) */
    $shareLabel = (string) setting('celebrations.screen.share_link_action', 'شارك الخبر');
    $shareCopiedLabel = (string) setting('celebrations.screen.share_copied', 'اتنسخ ✓');
    $shareFailedLabel = (string) setting('celebrations.screen.share_failed', 'انسخ الرابط من المتصفّح');
@endphp

{{-- شاشة احتفال قبول الحساب: كونفيتي + صوت + رسالة تهنئة + زرّ مشاركة (2.14-أ · 3) --}}
{{--
  والرسالة في `role="status"` فيقرؤها قارئ الشاشة — والكونفيتي `aria-hidden`
  زينةٌ لا معنى (2.16).
--}}
<div data-onboarding-celebration role="status" aria-live="polite"
     @class([
         'fixed inset-0 z-50 flex items-center justify-center p-4' => $tier === 3,
         'fixed inset-x-0 top-4 z-50 flex justify-center px-4' => $tier < 3,
     ])
     @style(['background: rgb(0 0 0 / .65)' => $tier === 3])>

    @if ($confetti)
        <x-confetti :variant="$confetti" />
    @endif

    <div @class([
            'card relative w-full max-w-md p-6 text-center animate-fadeup' => $tier === 3,
            'card relative p-3 px-4 text-sm flex items-center gap-2 animate-fadeup' => $tier < 3,
         ])>
        @if ($tier === 3)
            <div class="mx-auto mb-3" style="color: var(--color-state-honor)">
                <x-icon name="celebrate" size="56" :label="setting('celebrations.screen.icon_label', 'إنجاز')" />
            </div>

            {{-- رسالة التهنئة — نصفُ البند الغائب (2.14-أ · 3) --}}
            <h2 data-onboarding-celebration-message class="text-xl font-extrabold">{{ $message }}</h2>
            <p class="text-sm mt-1" style="color: var(--text-muted)">{{ $label }}</p>

            <div class="mt-5 flex items-center justify-center gap-2 flex-wrap">
                @if ($shareLink)
                    <button type="button"
                            data-onboarding-share="{{ $shareLink }}"
                            data-onboarding-share-text="{{ $message }}"
                            class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="min-height: 44px; background: var(--color-brand-500); color: #04201c">
                        {{ $shareLabel }}
                    </button>
                @endif

                <button type="button" data-onboarding-celebration-close
                        class="rounded-xl px-4 py-2 text-sm motion-standard"
                        style="min-height: 44px; background: var(--surface-sunken); color: var(--text)">
                    {{ setting('celebrations.screen.dismiss_action', 'تمام') }}
                </button>
            </div>
        @else
            <span aria-hidden="true" style="color: var(--color-state-honor)">
                <x-icon name="celebrate" size="18" />
            </span>
            <span data-onboarding-celebration-message>{{ $message }}</span>
            <button type="button" data-onboarding-celebration-close class="opacity-70 hover:opacity-100"
                    aria-label="{{ setting('celebrations.screen.close_aria', 'إغلاق') }}">
                <x-icon name="close" size="14" />
            </button>
        @endif
    </div>
</div>

@if (! empty($celebration['sound']) && ! empty($celebration['sound_path']))
    {{-- الصوت يخضع لتوجل الصوت، والأنيميشن حاضر دائمًا (2.14-ب) --}}
    <audio autoplay src="{{ \Illuminate\Support\Facades\Storage::url($celebration['sound_path']) }}"></audio>
@endif

{{-- السكربت مكتوبٌ هنا لا في مكدّس السكربتات: قالب الضيف لا يحمل مكدّسًا --}}
<script>
    (() => {
        const box = document.querySelector('[data-onboarding-celebration]');
        if (!box) return;

        /* المشاركة: واجهة النظام أوّلًا، والحافظة تراجعًا — وردٌّ فوريّ في الزرّ (2.17-ب) */
        const share = box.querySelector('[data-onboarding-share]');
        if (share) {
            share.addEventListener('click', async () => {
                const url = share.dataset.onboardingShare;
                const text = share.dataset.onboardingShareText || '';
                const done = (label) => {
                    const original = share.textContent;
                    share.textContent = label;
                    setTimeout(() => { share.textContent = original; }, 1800);
                };

                try {
                    if (navigator.share) { await navigator.share({ text: text, url: url }); return; }
                    await navigator.clipboard.writeText((text + ' ' + url).trim());
                    done(@json($shareCopiedLabel));
                } catch (e) { done(@json($shareFailedLabel)); }
            });
        }

        /* «قابلة للتخطّي بضغطة أو ESC، وتنتهي تلقائيًّا خلال ثوانٍ» (2.14-ب) */
        const close = () => box.remove();
        box.addEventListener('click', (e) => {
            if (e.target === box || e.target.closest('[data-onboarding-celebration-close]')) close();
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
        setTimeout(close, {{ $seconds * 1000 }});
    })();
</script>
