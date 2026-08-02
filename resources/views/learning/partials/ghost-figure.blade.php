@php
    /**
     * أصلا Ghost Timer الفنّيّان (الدستور 6 · 2.16-ج).
     *
     * الدستور يشترط أن يكون الشبح **رسمة/إليستريشن حقيقيّة لا أيقونة**، وكان
     * المطبَّق إيموجي (👻 و🧑‍🎓) يرسمه خطّ نظام التشغيل: يختلف شكله بين ويندوز
     * وأندرويد وiOS، ولا يتبع `currentColor` فلا يشارك في تدرّج الخطر من الأخضر
     * إلى الأحمر — وهو **جوهر** العنصر لا زخرفته.
     *
     * فهنا إليستريشن SVG مرسومة داخل المشروع: أجسام ممتلئة وتفاصيل وجه وذيل
     * متموّج، بلا أيّ مكتبة ولا أصل خارجيّ، وتتلوّن كلّها من `currentColor`.
     *
     * $figure = 'ghost' | 'hero'  ·  $size = القياس بالبكسل
     */
    $figure = $figure ?? 'ghost';
    $size = (int) ($size ?? 34);
@endphp

@if ($figure === 'ghost')
    <svg viewBox="0 0 48 48" width="{{ $size }}" height="{{ $size }}" aria-hidden="true"
         class="inline-block align-middle">
        {{-- الجسم: قبّة علويّة وذيل متموّج بثلاث موجات --}}
        <path fill="currentColor" fill-opacity="0.92"
              d="M24 4c-8.8 0-16 7.2-16 16v20.4c0 1.9 2.2 2.9 3.6 1.6l3.2-2.9a2.4 2.4 0 0 1 3.3.1l2.1 2.1a2.4 2.4 0 0 0 3.5 0l1.9-2a2.4 2.4 0 0 1 3.4 0l1.9 2a2.4 2.4 0 0 0 3.5 0l2-2.1a2.4 2.4 0 0 1 3.3-.1l1.7 1.5c1.4 1.3 3.6.3 3.6-1.6V20c0-8.8-7.2-16-16-16z" />
        {{-- العينان والفم: الوجه يقرأ فورًا حتّى في 20 بكسل --}}
        <ellipse cx="18" cy="19" rx="3" ry="4" fill="var(--surface)" />
        <ellipse cx="30" cy="19" rx="3" ry="4" fill="var(--surface)" />
        <ellipse cx="18.7" cy="20" rx="1.3" ry="1.7" fill="currentColor" />
        <ellipse cx="30.7" cy="20" rx="1.3" ry="1.7" fill="currentColor" />
        <path d="M20.5 27.5c1 1.6 2.2 2.4 3.5 2.4s2.5-.8 3.5-2.4" fill="none" stroke="var(--surface)"
              stroke-width="2" stroke-linecap="round" />
    </svg>
@else
    <svg viewBox="0 0 48 48" width="{{ $size }}" height="{{ $size }}" aria-hidden="true"
         class="inline-block align-middle">
        {{-- المتدرّب: قبّعة تخرّج + رأس + كتفان — الطرف الآخر من المسار (6) --}}
        <path fill="currentColor" fill-opacity="0.25" d="M8 41c0-7.2 7.2-11 16-11s16 3.8 16 11v3H8z" />
        <path fill="currentColor" fill-opacity="0.25" d="M24 15c4.4 0 8 3.6 8 8s-3.6 8-8 8-8-3.6-8-8 3.6-8 8-8z" />
        <path fill="currentColor" d="M24 4 6 11.5 24 19l18-7.5z" />
        <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
              d="M12 14v7c0 3.3 5.4 6 12 6s12-2.7 12-6v-7M40 12.5v8" />
    </svg>
@endif
