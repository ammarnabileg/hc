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
    .ghost-hero, .ghost-ghost { font-size: 1.35rem; line-height: 1; position: absolute; z-index: 1; }
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

    @media (prefers-reduced-motion: reduce) {
        .ghost-ghost, .ghost-shake { animation: none; }
    }

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
    .learning-scroll-x { overflow-x: auto; }
    .learning-scroll-x::-webkit-scrollbar { block-size: 4px; }
</style>
