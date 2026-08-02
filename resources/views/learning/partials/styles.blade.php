{{-- أنماط خاصّة بمجال التعلّم فقط — بلا أصول جديدة ولا مكتبات (2.10.1) --}}
<style>
    /* Ghost Timer (6): مسار أفقيّ يقترب فيه الشبح كلّما قرب الموعد */
    .ghost-track {
        position: relative;
        display: flex;
        align-items: center;
        block-size: 2.25rem;
    }
    .ghost-line {
        position: absolute;
        inset-inline: 1.75rem;
        block-size: 2px;
        background: repeating-linear-gradient(90deg, var(--border) 0 6px, transparent 6px 12px);
    }
    /* الأصلان رسمتان SVG لا إيموجي (6 · 2.16-ج) — والقياس من الرسمة نفسها */
    .ghost-hero, .ghost-ghost { line-height: 0; position: absolute; z-index: 1; }
    .ghost-hero { inset-inline-start: 0; }
    /* كلّما زادت النسبة المنقضية اقترب الشبح من المتدرّب (6) */
    .ghost-ghost {
        inset-inline-end: calc(var(--ghost-pos, 0%) * 0.78);
        transition: inset-inline-end 400ms var(--ease-standard);
        animation: ghost-float 2.4s ease-in-out infinite;
    }
    @keyframes ghost-float { 0%, 100% { translate: 0 0; } 50% { translate: 0 -4px; } }
    @keyframes ghost-shake {
        0%, 100% { rotate: 0deg; }
        25% { rotate: -8deg; }
        75% { rotate: 8deg; }
    }
    .ghost-shake { animation: ghost-shake 420ms var(--ease-standard) infinite; }

    /* الشبح يتحرّك دائمًا — التحكّم من إعداد المستخدم داخل المنصّة (app.css). */

    /* Roadmap رأسيّ بالسيكشنز (3.3 · 24.5) */
    .roadmap { position: relative; padding-inline-start: 1.25rem; }
    .roadmap::before {
        content: '';
        position: absolute;
        inset-block: 0.75rem;
        inset-inline-start: 0.3rem;
        inline-size: 2px;
        background: repeating-linear-gradient(180deg, var(--border) 0 6px, transparent 6px 12px);
    }
    .roadmap-node { position: relative; }
    .roadmap-node::before {
        content: '';
        position: absolute;
        inset-inline-start: -1.2rem;
        inset-block-start: 1.05rem;
        inline-size: 0.65rem;
        block-size: 0.65rem;
        border-radius: 999px;
        background: var(--surface-sunken);
        border: 2px solid var(--border);
    }
    .roadmap-node[data-done='1']::before { background: var(--color-brand-500); border-color: var(--color-brand-500); }
    .roadmap-node[data-current='1']::before { background: var(--color-brand-300); border-color: var(--color-brand-300); }

    /* ⭐ أنيميشن فتح المحطّة (3.4-23): المحطّة التي صارت متاحة تنبض مرّةً وتُضيء
       حلقتُها — إشارة «افتحني» لا زخرفة، ولذلك تقع مرّة واحدة ثمّ تسكن. */
    @keyframes station-unlock {
        0%   { transform: scale(.85); box-shadow: 0 0 0 0 var(--color-brand-500); }
        60%  { transform: scale(1.25); box-shadow: 0 0 0 6px transparent; }
        100% { transform: scale(1); box-shadow: 0 0 0 0 transparent; }
    }
    .roadmap-node[data-unlocked='1']::before { animation: station-unlock 900ms var(--ease-standard) 2; }

    /* ⭐ علامة الإكمال «بتتملّى» (3.4-33): الخلفيّة تمتلئ من البداية للنهاية مرّةً */
    @keyframes check-fill {
        from { background-size: 0% 100%; }
        to   { background-size: 100% 100%; }
    }
    .check-fill {
        background-image: linear-gradient(90deg, color-mix(in srgb, var(--color-state-ok) 22%, transparent) 0 100%);
        background-repeat: no-repeat;
        animation: check-fill 620ms var(--ease-standard) forwards;
    }

    [data-motion='off'] .roadmap-node[data-unlocked='1']::before,
    [data-motion='off'] .check-fill { animation: none; }

    /* الإدخال الرقميّ بنمط OTP (4): خانة لكلّ رقم — ومقاس اللمس 44×44 (2.15-ج) */
    .otp-row { display: flex; gap: 0.5rem; flex-wrap: wrap; direction: ltr; justify-content: flex-end; }
    .otp-box {
        inline-size: 2.75rem;
        block-size: 2.75rem;
        text-align: center;
        font-size: 1.1rem;
        font-weight: 700;
        border-radius: 0.75rem;
        background: var(--surface-sunken);
        border: 1px solid var(--border);
        color: var(--text);
    }
    .otp-box:focus { outline: 2px solid var(--color-brand-500); outline-offset: 1px; }

    /* بانل قائمة الدروس: جانبيّ على الديسكتوب، وBottom Sheet على الموبايل (24.5) */
    @media (max-width: 767px) {
        .lesson-panel[open] .lesson-panel-body {
            position: fixed;
            inset-inline: 0;
            inset-block-end: 0;
            max-block-size: 70vh;
            overflow-y: auto;
            z-index: 45;
            background: var(--surface);
            border-block-start: 1px solid var(--border);
            border-start-start-radius: 1rem;
            border-start-end-radius: 1rem;
            padding-block-end: 5rem;
        }
    }

    /* ممنوع التمرير الأفقيّ على الموبايل — شرط قبول (2.15-ج) */
    .roadmap, .roadmap * { min-inline-size: 0; }

    /* Skeleton دفعة التعليقات (3.1): مكان محجوز بدل قفزة فراغ أثناء الجلب */
    .skeleton-line {
        block-size: 0.7rem;
        inline-size: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, var(--surface-raised) 0 40%, var(--border) 50%, var(--surface-raised) 60% 100%);
        background-size: 300% 100%;
        animation: skeleton-sweep 1.4s ease-in-out infinite;
    }
    @keyframes skeleton-sweep { 0% { background-position: 100% 0; } 100% { background-position: -100% 0; } }

    /* لمعة الـSkeleton من إعداد المستخدم داخل المنصّة (app.css). */

    /* قسم التعليقات: سهم الطيّ بدل مثلّث المتصفّح الافتراضيّ */
    details[data-comments] > summary::-webkit-details-marker { display: none; }
    details[data-comments] > summary::after {
        content: '⌄';
        margin-inline-start: auto;
        transition: rotate 200ms var(--ease-standard);
    }
    details[data-comments][open] > summary::after { rotate: 180deg; }
</style>
