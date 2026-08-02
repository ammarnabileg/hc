@php
    /**
     * الاحتفال بمستواه (2.14) — ثلاثة مستويات لا رابع:
     *  1 خفيف: بلا صوت — Toast + نبضة.
     *  2 متوسّط: كونفيتي خفيف + صوت قصير.
     *  3 ذروة: شاشة احتفال كاملة + زرّ مشاركة.
     * كلّه CSS-first بلا أصول جديدة، وقابل للتخطّي بضغطة أو ESC، وينتهي تلقائيًّا.
     * والاستهلاك مُسجَّل Server-side فلا يتكرّر بإعادة التحميل.
     */
    $tier = (int) ($celebration['tier'] ?? 1);
    $pieces = $tier === 3 ? 28 : ($tier === 2 ? 14 : 0);
    $seconds = (int) setting('celebrations.auto_dismiss_seconds', 6);
@endphp

<div data-celebration data-tier="{{ $tier }}" role="status" aria-live="polite"
     @class([
         'fixed inset-0 z-50 flex items-center justify-center p-4' => $tier === 3,
         'fixed inset-x-0 top-4 z-50 flex justify-center px-4' => $tier < 3,
     ])
     @style(['background: rgb(0 0 0 / .65)' => $tier === 3])>

    @if ($pieces > 0)
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            @for ($i = 0; $i < $pieces; $i++)
                <span class="celebrate-piece"
                      style="inset-inline-start: {{ (int) (($i * 97) % 100) }}%;
                             animation-delay: {{ $i * 60 }}ms;
                             background: {{ ['var(--color-brand-400)', 'var(--color-state-honor)', 'var(--color-brand-200)'][$i % 3] }}"></span>
            @endfor
        </div>
    @endif

    <div @class([
            'card relative w-full max-w-md p-6 text-center animate-fadeup' => $tier === 3,
            'card relative p-3 px-4 text-sm flex items-center gap-2 animate-fadeup' => $tier < 3,
         ])>
        @if ($tier === 3)
            <div class="mx-auto mb-3 celebrate-pulse" style="color: var(--color-state-honor)">
                @include('challenges.components.war-icon', ['type' => 'default', 'size' => 64, 'label' => 'إنجاز'])
            </div>
            <h2 class="text-xl font-extrabold">{{ $celebration['message'] }}</h2>
            <p class="text-sm mt-1" style="color: var(--text-muted)">{{ $celebration['label'] }}</p>

            <div class="mt-5 flex items-center justify-center gap-2">
                @if (! empty($shareUrl))
                    {{-- لقطة إنجاز قابلة للمشاركة (2.17-أ) — يبنيها استوديو الصور --}}
                    <a href="{{ $shareUrl }}"
                       class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">لقطة إنجاز</a>
                @endif
                <button type="button" data-celebration-close
                        class="rounded-xl px-4 py-2 text-sm motion-standard"
                        style="background: var(--surface-sunken); color: var(--text)">تمام</button>
            </div>
        @else
            <span class="celebrate-pulse" aria-hidden="true"><x-icon name="celebrate" size="16" /></span>
            <span>{{ $celebration['message'] }}</span>
            <button type="button" data-celebration-close class="opacity-70 hover:opacity-100" aria-label="إغلاق">✕</button>
        @endif
    </div>
</div>

@if (! empty($celebration['sound']) && ! empty($celebration['sound_path']))
    {{-- الصوت يخضع لتوجل الصوت، والأنيميشن حاضر دائمًا --}}
    <audio autoplay src="{{ \Illuminate\Support\Facades\Storage::url($celebration['sound_path']) }}"></audio>
@endif

@push('scripts')
    <style>
        @keyframes celebrate-fall {
            from { transform: translateY(-10vh) rotate(0deg); opacity: 1; }
            to   { transform: translateY(105vh) rotate(540deg); opacity: 0; }
        }
        .celebrate-piece {
            position: absolute;
            inset-block-start: -10vh;
            inline-size: 8px;
            block-size: 14px;
            border-radius: 2px;
            animation: celebrate-fall 2.6s var(--ease-standard) forwards;
        }
        @keyframes celebrate-pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.08); } }
        .celebrate-pulse { display: inline-block; animation: celebrate-pulse 1.4s var(--ease-standard) infinite; }
        /* بلا استعلام وسائط نظام التشغيل: الكونفيتي والنبضة ذروة 2.9-6،
           والتحكّم فيهما من إعداد المستخدم داخل المنصّة (app.css). */
    </style>
    <script>
        (() => {
            const box = document.querySelector('[data-celebration]');
            if (!box) return;
            const close = () => box.remove();
            box.addEventListener('click', (e) => {
                if (e.target.closest('[data-celebration-close]') || e.target === box) close();
            });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
            setTimeout(close, {{ $seconds * 1000 }});
        })();
    </script>
@endpush
