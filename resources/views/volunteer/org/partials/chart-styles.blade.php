@php
    /**
     * أنماط كانفاس الهيكل (13.4-م-3) — CSS خاصّ بمجالنا لا يمسّ نظام التصميم المشترك.
     * الحركة بسرعة المنصّة الموحّدة ومنحناها (2.17-د).
     */
@endphp

<style>
    .org-stage {
        position: relative;
        overflow: hidden;
        block-size: 68vh;
        min-block-size: 420px;
        touch-action: none;
        cursor: grab;
        background:
            radial-gradient(circle at 1px 1px, color-mix(in srgb, var(--color-brand-500) 12%, transparent) 1px, transparent 0)
            0 0 / 28px 28px;
    }
    .org-stage.is-panning { cursor: grabbing; }

    /*
     | هندسة الكانفاس فيزيائيّة عمدًا (top/left لا inset-inline):
     | إحداثيّات العقد والخطوط واحدة، فلا ينقلب الأصل مع اتّجاه الصفحة RTL.
     */
    .org-world { position: absolute; top: 0; left: 0; transform-origin: 0 0; }
    .org-links { position: absolute; top: 0; left: 0; overflow: visible; pointer-events: none; }

    .org-node {
        position: absolute;
        inline-size: 200px;
        min-block-size: 104px;
        padding: 10px;
        border-radius: 1rem;
        background: var(--surface-raised);
        border: 1px solid var(--border);
        cursor: grab;
        transition: box-shadow 200ms var(--ease-standard), border-color 200ms var(--ease-standard);
    }
    .org-node:active { cursor: grabbing; }
    .org-node.is-me { border-color: var(--color-brand-500); }
    /* إطار ذهبيّ لعضو نادي التميّز — الهيكل نفسه يحمل تقديرًا (13.4-م) */
    .org-node.is-club { border-color: var(--color-state-honor); box-shadow: inset 0 0 0 1px var(--color-state-honor); }
    /* عقدة «أخوكم»: مميّزة هادئة وبلا أيّ مؤشّر تشغيليّ (13.4-ص) */
    .org-node.is-honorary {
        border-color: var(--color-state-honor);
        background: color-mix(in srgb, var(--color-state-honor) 7%, var(--surface-raised));
    }
    .org-node.is-hit { box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-brand-500) 45%, transparent); }

    .org-node__avatar {
        inline-size: 34px; block-size: 34px; border-radius: 999px;
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--surface-sunken); color: var(--text-muted);
        font-size: 12px; font-weight: 700; overflow: hidden; flex: 0 0 auto;
        box-shadow: none; /* الأفاتار بلا هالة */
    }
    .org-node__avatar img { inline-size: 100%; block-size: 100%; object-fit: cover; }
    .org-node__name { font-weight: 700; font-size: 13px; }
    .org-node__meta { font-size: 11px; color: var(--text-muted); }
    .org-node__chip { font-size: 10px; border-radius: 999px; padding: 1px 6px; }

    .org-bar { block-size: 4px; border-radius: 999px; background: var(--surface-sunken); overflow: hidden; }
    .org-bar > span { display: block; block-size: 100%; }

    .org-expand {
        position: absolute; bottom: -13px; left: 50%; transform: translateX(-50%);
        font-size: 10px; border-radius: 999px; padding: 2px 8px; border: 1px solid var(--border);
        background: var(--surface); color: var(--text); cursor: pointer;
    }
</style>
