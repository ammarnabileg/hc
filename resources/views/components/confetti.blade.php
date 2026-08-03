@props(['variant' => 'light'])

@php
    /**
     * 🎉 الكونفيتي — **مصدر واحد** في المنصّة كلّها (2.14-ب: «الاتّساق: مصدر
     * واحد للاحتفالات في كلّ المنصّة … لا نسخ متعدّدة»).
     *
     * والشكل من النصّ حرفيًّا:
     *  • 4.1 — «**احتفال إنهاء الفيديو/الدرس: كونفيتي بينزل من فوق لتحت
     *    (Confetti Rain) لحظة الإكمال** — لحظة ذروة (Peak-End، راجع 2.9-#6)»
     *    ⟵ `variant = rain`.
     *  • 2.14-أ — المستوى **2 متوسّط**: «**كونفيتي خفيف + صوت قصير**»
     *    ⟵ `variant = light`.
     *  • 2.14-أ — المستوى **3 ذروة**: «شاشة احتفال كاملة: **كونفيتي غزير** …»
     *    ⟵ `variant = peak`.
     *
     * ولا رقم محروق (2.13): **العدد والمدّة والشدّة** كلّها من `setting()`
     * فيملكها المالك من تاب الاحتفالات في لوحة الإدارة.
     *
     * والحركة **حاضرة دائمًا** (2.3 · 2.14-ب): لا سمة تُطفئها ولا استعلام
     * وسائط نظام تشغيل — `prefers-reduced-motion` مرفوضٌ بالاسم في 2.3.
     */
    $pieces = max(0, (int) match ($variant) {
        // كونفيتي إنهاء الدرس (4.1) — نزولٌ من فوق لتحت
        'rain' => setting('celebrations.confetti.rain_pieces', 48),
        // كونفيتي غزير للذروة (2.14-أ · المستوى 3)
        'peak' => setting('celebrations.confetti.pieces', 80),
        // كونفيتي خفيف (2.14-أ · المستوى 2)
        default => setting('celebrations.confetti.light_pieces', 20),
    });

    // المدّة والشدّة: زمن نزول القطعة · تباعد الإطلاق · مقاس القطعة
    $fallMs = max(400, (int) setting('celebrations.confetti.fall_ms', 2800));
    $staggerMs = max(0, (int) setting('celebrations.confetti.stagger_ms', 60));
    $width = max(2, (int) setting('celebrations.confetti.piece_width_px', 8));
    $height = max(2, (int) setting('celebrations.confetti.piece_height_px', 14));

    $wave = max(1, (int) ceil($pieces / 3));
    $palette = ['var(--color-brand-400)', 'var(--color-state-honor)', 'var(--color-brand-200)'];
@endphp

@if ($pieces > 0)
    {{--
      الحاوية بمقاس الشاشة و`overflow-hidden`: القطع تنزل من فوقها إلى تحتها
      **بلا تمرير أفقيّ** ولو على 375px (2.15-ج). و`aria-hidden` لأنّها زينة —
      والرسالة نفسها تُقرأ من `role="status"` في بطاقة الاحتفال.
    --}}
    <div data-confetti="{{ $variant }}" data-confetti-pieces="{{ $pieces }}"
         class="confetti-stage pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
        @for ($i = 0; $i < $pieces; $i++)
            @php
                // توزيع منتظم + إزاحة محسوبة كي لا تصطفّ القطع كمشط
                $left = min(97, max(0, ($i * 100 / $pieces) + ((($i * 37) % 7) - 3)));
            @endphp
            <span class="confetti-piece"
                  style="inset-inline-start: {{ round($left, 2) }}%;
                         animation-delay: {{ ($i % $wave) * $staggerMs }}ms;
                         animation-duration: {{ $fallMs + (($i % 5) * 220) }}ms;
                         inline-size: {{ $width }}px; block-size: {{ $height }}px;
                         background: {{ $palette[$i % 3] }}"></span>
        @endfor
    </div>

    @once
        <style>
            /* من فوق لتحت حرفيًّا (4.1): يبدأ فوق الشاشة وينتهي تحتها */
            @keyframes confetti-fall {
                from { transform: translate3d(0, -14vh, 0) rotate(0deg); opacity: 1; }
                to   { transform: translate3d(0, 108vh, 0) rotate(540deg); opacity: 0; }
            }

            .confetti-piece {
                position: absolute;
                inset-block-start: 0;
                border-radius: 2px;
                animation-name: confetti-fall;
                animation-timing-function: var(--ease-standard, cubic-bezier(.2, .8, .2, 1));
                animation-fill-mode: forwards;
            }

            /* ⛔ لا قاعدة تُطفئ هذه الحركة — «الأنيميشن حاضر دائمًا» (2.3 · 2.14-ب) */
        </style>
    @endonce
@endif
