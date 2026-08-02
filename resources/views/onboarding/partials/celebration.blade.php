@php
    /**
     * احتفال رحلة التسجيل (2.14): ثلاثة مستويات لا رابع —
     * 1 خفيف بلا صوت · 2 كونفيتي خفيف · 3 شاشة كاملة.
     *
     * CSS-first بلا أصول جديدة ولا مكتبات. **الصوت وحده** يخضع لتوجل المستخدم،
     * والأنيميشن حاضرٌ دائمًا لأنّه روح المنصّة — ولا يُوقَف بـ`prefers-reduced-motion`.
     */
    $tier = (int) ($celebration['tier'] ?? 3);
    $pieces = $tier === 3 ? 28 : ($tier === 2 ? 14 : 0);
@endphp

@if ($pieces > 0)
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true" style="z-index: 40">
        @for ($i = 0; $i < $pieces; $i++)
            <span class="ob-piece"
                  style="inset-inline-start: {{ (int) (($i * 97) % 100) }}%;
                         animation-delay: {{ $i * 60 }}ms;
                         background: {{ ['var(--color-brand-400)', 'var(--color-state-honor)', 'var(--color-brand-200)'][$i % 3] }}"></span>
        @endfor
    </div>

    <style>
        @keyframes ob-fall {
            from { transform: translateY(-10vh) rotate(0deg); opacity: 1; }
            to   { transform: translateY(105vh) rotate(540deg); opacity: 0; }
        }
        .ob-piece {
            position: absolute; inset-block-start: -10vh;
            inline-size: 8px; block-size: 14px; border-radius: 2px;
            animation: ob-fall 2.8s var(--ease-standard, ease-out) forwards;
        }
    </style>
@endif

@if (! empty($celebration['sound']) && ! empty($celebration['sound_path']))
    <audio autoplay src="{{ \Illuminate\Support\Facades\Storage::url($celebration['sound_path']) }}"></audio>
@endif
