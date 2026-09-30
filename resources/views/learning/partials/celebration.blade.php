@php
    /**
     * احتفالات مسار التعلّم (2.14 · 2.9-6 · 4.1 · 3.4-18 · 3.4-22 · 3.4-23).
     *
     * الاحتفال يصل من الخادم مفلوشًا في السيشن بعد إكمال درس، وقراره كلّه هناك:
     * المستوى والنصّ والصوت والاستهلاك مرّةً واحدة — والواجهة **تعرض** فقط.
     *
     * ⭐ **مَن يستحقّ أيّ مستوى، وما شكل كلٍّ — بالنصّ:**
     *
     *  • **المستوى** من 2.14-أ وحدها، وهي جدول «لكلّ حدث مستواه»:
     *    «**1 — خفيف (Micro)**: **بلا صوت**: Toast + حركة صغيرة … **إكمال
     *    درس**»، و«**2 — متوسّط (Mid)**: **كونفيتي خفيف + صوت قصير** … إتمام
     *    تدريب · بلوغ مستوى جديد»، و«**3 — ذروة (Peak)**: **شاشة احتفال
     *    كاملة**: كونفيتي غزير + صوت + رسالة تهنئة + **زرّ مشاركة**».
     *    فإكمال الدرس **مستواه 1**: بلا صوت، وبلا شاشة تحجب، وبلا زرّ مشاركة.
     *
     *  • **وشكل لحظة إنهاء الدرس** من 4.1 وهي نصٌّ خاصٌّ بهذه اللحظة بعينها:
     *    «**احتفال إنهاء الفيديو/الدرس: كونفيتي بينزل من فوق لتحت (Confetti
     *    Rain) لحظة الإكمال** — لحظة ذروة (Peak-End، راجع 2.9-#6)».
     *    فالكونفيتي هنا **فرضٌ منصوص** لا زينة اختياريّة.
     *
     *  فالمستوى من 2.14 والشكل من 4.1 — ولا تعارض: النصّان يجتمعان في
     *  «شريط + كونفيتي نازل بلا صوت»، ولو رُفِع الحدث للمستوى 3 لخالفنا جدول
     *  2.14-أ وأضفنا صوتًا وشاشةً حاجبةً لم يطلبهما النصّ، ولخضع الكونفيتي
     *  لـ«الحدّ اليوميّ للذروة» فينطفئ بعد ثلاثة دروس — وهو عين ما ينفيه 4.1.
     *
     * ومع كلّ احتفالٍ **+XP بيطير لأعلى** (3.4-18) بالقيمة المكتسبة فعلًا،
     * وحدث `level.up` يزيد **أنيميشن Level Up** (3.4-22).
     *
     * كلّه CSS-first بلا أصول جديدة (2.14-ب)، قابل للتخطّي بضغطة أو `ESC`،
     * وينتهي تلقائيًّا. و**الأنيميشن حاضر دائمًا** (2.3 · 2.14-ب) — لا توجّل
     * يطفئه ولا `prefers-reduced-motion`.
     */
    $tier = (int) ($celebration['tier'] ?? 1);
    $key = (string) ($celebration['key'] ?? '');
    $isLevelUp = $key === 'level.up';

    /*
     | أيّ كونفيتي لهذه اللحظة — والعدد والمدّة والشدّة كلّها من `setting()`
     | داخل `<x-confetti>` نفسه (2.13: لا رقم محروق).
     */
    $confetti = match (true) {
        $tier === 3 => 'peak',              // كونفيتي غزير (2.14-أ · 3)
        $key === 'lesson.completed' => 'rain', // كونفيتي بينزل من فوق لتحت (4.1)
        $tier === 2 => 'light',             // كونفيتي خفيف (2.14-أ · 2)
        default => null,
    };

    $seconds = max(2, (int) setting('celebrations.auto_dismiss_seconds', 6));
    $xp = (int) ($celebration['xp'] ?? 0);

    /*
     | ⭐ لقطة إنجاز جاهزة (الفكرة #22): بطاقة بالاسم والإنجاز والتاريخ وهويّة
     | المنصّة الثابتة، يبنيها استوديو الصور من حمولةٍ موقَّعة. تظهر للإنجازات من
     | الدرجة الثانية فأعلى بعد النجاح الحقيقيّ، ولا تعيق المتابعة (رابط يفتح في
     | تبويبٍ آخر). مَن لا يملك صلاحيّة الاستخراج لا يرى الزرّ (2.15-أ-7).
     */
    $shareUrl = $shareUrl ?? null;
    $viewer = auth()->user();

    $mayShare = $viewer && $tier >= 2 && (
        \Illuminate\Support\Facades\Gate::allows('image_export.use')
        || (bool) setting('learning.celebration.share_snapshot_enabled', true)
    );

    if (empty($shareUrl) && $mayShare) {
        $snapshot = new \App\Services\Images\BoardSnapshot(
            'card',
            (string) ($celebration['message'] ?? ''),
            str_replace(':date', now()->format('Y/m/d'), (string) setting('learning.celebration.share_subtitle', 'بتاريخ :date')),
            [['rank' => 1, 'u' => $viewer->id, 'name' => $viewer->name, 'value' => (string) ($celebration['label'] ?? '')]],
        );
        // التوقيع على الحمولة وحدها؛ خيارات الشكل (top/download) خارج التوقيع كما يقرأها المتحكّم
        $shareRoute = \Illuminate\Support\Facades\Gate::allows('image_export.use') ? 'export.image' : 'export.self-card';
        $shareUrl = \Illuminate\Support\Facades\URL::signedRoute($shareRoute, ['d' => $snapshot->encode()]).'&top=me&download=0';
    }
@endphp

<div data-learn-celebration role="status" aria-live="polite"
     @class([
         'fixed inset-0 z-50 flex items-center justify-center p-4' => $tier === 3,
         'fixed inset-x-0 top-4 z-50 flex justify-center px-4' => $tier < 3,
     ])
     @style(['background: rgb(0 0 0 / .65)' => $tier === 3])>

    @if ($confetti)
        {{-- «كونفيتي بينزل من فوق لتحت (Confetti Rain) لحظة الإكمال» — 4.1 --}}
        <x-confetti :variant="$confetti" />
    @endif

    <div @class([
            'card relative w-full max-w-md p-6 text-center animate-fadeup' => $tier === 3,
            'card relative p-3 px-4 text-sm flex items-center gap-2 animate-fadeup' => $tier < 3,
         ])>
        @if ($tier === 3)
            @php $isStamp = ! $isLevelUp && in_array($celebration['key'] ?? '', ['course.completed', 'certificate.issued', 'path.completed'], true); @endphp
            <div class="mx-auto mb-3 {{ $isLevelUp ? 'learn-levelup' : '' }} {{ $isStamp ? 'stamp-press' : '' }}" style="color: var(--color-state-honor)">
                @if ($isStamp)
                    {{-- ⭐ ختم الإنجاز المشترك (الفكرة #9): نفس الختم في الاحتفال وبطاقة المشاركة --}}
                    <x-achievement-stamp size="72" />
                @else
                    <x-icon :name="$isLevelUp ? 'level' : 'celebrate'" size="56" />
                @endif
            </div>
            <h2 class="text-xl font-extrabold">{{ $celebration['message'] }}</h2>
            <p class="text-sm mt-1" style="color: var(--text-muted)">{{ $celebration['label'] }}</p>
            @if (! empty($celebration['rank']))
                {{-- ⭐ قبل وبعد في سطر (الفكرة #23): فرقٌ موثَّق من لوحة XP لا تخمين --}}
                <p class="mt-2 inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold" data-celebration-rank
                   style="background: var(--brand-soft); color: var(--brand)">
                    <x-icon name="level" size="14" /> {{ strtr((string) setting('learning.celebration.rank_line', 'ترتيبك اتحسّن من :from إلى :to'), [':from' => $celebration['rank']['from'], ':to' => $celebration['rank']['to']]) }}
                </p>
            @endif

            <div class="mt-5 flex items-center justify-center gap-2 flex-wrap">
                {{-- ⭐ مشاركة إنجاز (3.4-47) — لقطة يبنيها استوديو الصور --}}
                @if (! empty($shareUrl))
                    <a href="{{ $shareUrl }}" target="_blank" rel="noopener" data-celebration-share
                       class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500)">
                        {{ setting('learning.celebration.share_cta', 'شارك إنجازك') }}
                    </a>
                @endif
                <button type="button" data-learn-celebration-close
                        class="rounded-xl px-4 py-2 text-sm motion-standard"
                        style="background: var(--surface-sunken); color: var(--text)">
                    {{ setting('celebrations.labels.close', 'تمام') }}
                </button>
            </div>
        @else
            @php $isStamp = ! $isLevelUp && in_array($celebration['key'] ?? '', ['course.completed', 'certificate.issued', 'path.completed'], true); @endphp
            <span aria-hidden="true" class="{{ $isLevelUp ? 'learn-levelup' : '' }} {{ $isStamp ? 'stamp-press' : '' }}" style="color: var(--color-state-honor)">
                @if ($isStamp)
                    {{-- ⭐ ختم الإنجاز المشترك (الفكرة #9) في الشريط المختصر أيضًا --}}
                    <x-achievement-stamp size="22" />
                @else
                    <x-icon :name="$isLevelUp ? 'level' : 'celebrate'" size="18" />
                @endif
            </span>
            <span>{{ $celebration['message'] }}</span>
            @if (! empty($shareUrl))
                {{-- ⭐ لقطة إنجاز جاهزة (الفكرة #22) في الشريط المختصر: رابط لا يعيق المتابعة --}}
                <a href="{{ $shareUrl }}" target="_blank" rel="noopener" data-celebration-share class="text-xs underline" style="color: var(--brand)">{{ setting('learning.celebration.share_cta', 'شارك إنجازك') }}</a>
            @endif
            @if (! empty($celebration['rank']))
                <span class="text-xs" data-celebration-rank style="color: var(--text-muted)">· {{ strtr((string) setting('learning.celebration.rank_line', 'ترتيبك اتحسّن من :from إلى :to'), [':from' => $celebration['rank']['from'], ':to' => $celebration['rank']['to']]) }}</span>
            @endif
            <button type="button" data-learn-celebration-close class="opacity-70 hover:opacity-100"
                    aria-label="{{ setting('celebrations.labels.close', 'تمام') }}">
                <x-icon name="close" size="14" />
            </button>
        @endif
    </div>

    {{-- ⭐ +XP بيطير لأعلى مع كلّ إنجاز (3.4-18) — والرقم النهائيّ ظاهر دائمًا (2.17) --}}
    @if ($xp > 0)
        <span class="learn-xp-fly pointer-events-none" aria-hidden="true">
            +{{ number_format($xp) }} {{ setting('learning.xp.suffix', 'XP') }}
        </span>
    @endif
</div>

@if (! empty($celebration['sound']) && ! empty($celebration['sound_path']))
    {{-- الصوت يخضع لتوجل الصوت، والأنيميشن حاضر دائمًا (2.14-ب) --}}
    <audio autoplay src="{{ \Illuminate\Support\Facades\Storage::url($celebration['sound_path']) }}"></audio>
@endif

@push('scripts')
    <style>
        @keyframes learn-xp-fly {
            0%   { transform: translate(-50%, 0) scale(.9); opacity: 0; }
            18%  { transform: translate(-50%, -12vh) scale(1.15); opacity: 1; }
            100% { transform: translate(-50%, -42vh) scale(1); opacity: 0; }
        }
        .learn-xp-fly {
            position: fixed; inset-block-end: 22vh; inset-inline-start: 50%;
            font-weight: 800; font-size: 1.5rem; color: var(--color-brand-400);
            animation: learn-xp-fly 2.2s var(--ease-standard) forwards;
        }

        @keyframes learn-levelup {
            0%, 100% { transform: scale(1) rotate(0deg); }
            30%      { transform: scale(1.25) rotate(-6deg); }
            60%      { transform: scale(1.12) rotate(6deg); }
        }
        .learn-levelup { display: inline-block; animation: learn-levelup 1.1s var(--ease-standard) 2; }

        /* ⛔ لا قاعدة تُطفئ ما سبق — «الأنيميشن حاضر دائمًا» (2.3 · 2.14-ب) */
    </style>
    <script>
        (() => {
            const box = document.querySelector('[data-learn-celebration]');
            if (!box) return;
            const close = () => box.remove();
            box.addEventListener('click', (e) => {
                if (e.target === box || e.target.closest('[data-learn-celebration-close]')) close();
            });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
            setTimeout(close, {{ $seconds * 1000 }});
        })();
    </script>
@endpush
