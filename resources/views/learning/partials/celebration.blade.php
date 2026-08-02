@php
    /**
     * احتفالات مسار التعلّم (2.14 · 2.9-6 · 4.1 · 3.4-18 · 3.4-22 · 3.4-23).
     *
     * الاحتفال يصل من الخادم مفلوشًا في السيشن بعد إكمال درس، وقراره كلّه هناك:
     * المستوى والنصّ والصوت والاستهلاك مرّةً واحدة — والواجهة **تعرض** فقط.
     *
     * ثلاثة مستويات لا رابع:
     *  1 خفيف: شريط علويّ بلا صوت.
     *  2 متوسّط: كونفيتي خفيف.
     *  3 ذروة: **كونفيتي بينزل من فوق لتحت (Confetti Rain)** كما ينصّ 4.1 حرفيًّا.
     *
     * ومع كلّ احتفالٍ **+XP بيطير لأعلى** (3.4-18) بالقيمة المكتسبة فعلًا،
     * وحدث `level.up` يزيد **أنيميشن Level Up** (3.4-22).
     *
     * كلّه CSS، قابل للتخطّي بضغطة أو ESC، وينتهي تلقائيًّا، ويحترم
     * تفضيل نظام التشغيل — والتحكّم من توجل الحركة داخل المنصّة وحده.
     */
    $tier = (int) ($celebration['tier'] ?? 1);
    $isLevelUp = ($celebration['key'] ?? '') === 'level.up';
    $pieces = $tier === 3 ? 36 : ($tier === 2 ? 16 : 0);
    $seconds = max(2, (int) setting('celebrations.auto_dismiss_seconds', 6));
    $xp = (int) ($celebration['xp'] ?? 0);
@endphp

<div data-learn-celebration role="status" aria-live="polite"
     @class([
         'fixed inset-0 z-50 flex items-center justify-center p-4' => $tier === 3,
         'fixed inset-x-0 top-4 z-50 flex justify-center px-4' => $tier < 3,
     ])
     @style(['background: rgb(0 0 0 / .65)' => $tier === 3])>

    @if ($pieces > 0)
        {{-- Confetti Rain: من فوق لتحت حرفيًّا (4.1) --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            @for ($i = 0; $i < $pieces; $i++)
                <span class="learn-piece"
                      style="inset-inline-start: {{ (int) (($i * 61) % 100) }}%;
                             animation-delay: {{ $i * 45 }}ms;
                             background: {{ ['var(--color-brand-400)', 'var(--color-state-honor)', 'var(--color-brand-200)'][$i % 3] }}"></span>
            @endfor
        </div>
    @endif

    <div @class([
            'card relative w-full max-w-md p-6 text-center animate-fadeup' => $tier === 3,
            'card relative p-3 px-4 text-sm flex items-center gap-2 animate-fadeup' => $tier < 3,
         ])>
        @if ($tier === 3)
            <div class="mx-auto mb-3 {{ $isLevelUp ? 'learn-levelup' : '' }}" style="color: var(--color-state-honor)">
                <x-icon :name="$isLevelUp ? 'level' : 'celebrate'" size="56" />
            </div>
            <h2 class="text-xl font-extrabold">{{ $celebration['message'] }}</h2>
            <p class="text-sm mt-1" style="color: var(--text-muted)">{{ $celebration['label'] }}</p>

            <div class="mt-5 flex items-center justify-center gap-2 flex-wrap">
                {{-- ⭐ مشاركة إنجاز (3.4-47) — لقطة يبنيها استوديو الصور --}}
                @if (! empty($shareUrl))
                    <a href="{{ $shareUrl }}"
                       class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--color-brand-500); color: #04201c">
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
            <span aria-hidden="true" class="{{ $isLevelUp ? 'learn-levelup' : '' }}" style="color: var(--color-state-honor)">
                <x-icon :name="$isLevelUp ? 'level' : 'celebrate'" size="18" />
            </span>
            <span>{{ $celebration['message'] }}</span>
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
        @keyframes learn-fall {
            from { transform: translateY(-12vh) rotate(0deg); opacity: 1; }
            to   { transform: translateY(108vh) rotate(600deg); opacity: 0; }
        }
        .learn-piece {
            position: absolute; inset-block-start: -12vh;
            inline-size: 8px; block-size: 14px; border-radius: 2px;
            animation: learn-fall 2.8s var(--ease-standard) forwards;
        }

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

        [data-motion="off"] .learn-piece,
        [data-motion="off"] .learn-xp-fly,
        [data-motion="off"] .learn-levelup { animation: none; }
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
