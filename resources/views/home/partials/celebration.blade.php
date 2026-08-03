@php
    /**
     * احتفال بلوغ لقب السفير (2.14) — بمستواه المضبوط من شاشة الاحتفالات،
     * وثلاثة مستويات لا رابع: 1 خفيف بلا صوت · 2 كونفيتي خفيف · 3 شاشة كاملة.
     * CSS-first بلا أصول جديدة، وقابل للتخطّي بضغطة أو ESC، وينتهي تلقائيًّا.
     */
    $tier = (int) ($celebration['tier'] ?? 1);
    // العدد والمدّة والشدّة من `setting()` داخل `<x-confetti>` (2.13)
    $confetti = match ($tier) {
        3 => 'peak',    // «كونفيتي غزير» (2.14-أ · 3)
        2 => 'light',   // «كونفيتي خفيف» (2.14-أ · 2)
        default => null, // «بلا صوت: Toast + حركة صغيرة» (2.14-أ · 1)
    };
    $seconds = max(2, (int) setting('celebrations.auto_dismiss_seconds', 6));
@endphp

<div data-home-celebration role="status" aria-live="polite"
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
                @include('home.partials.icon', ['name' => 'crown', 'size' => 56])
            </div>
            <h2 class="text-xl font-extrabold">{{ $celebration['message'] }}</h2>
            <p class="text-sm mt-1" style="color: var(--text-muted)">{{ $celebration['label'] }}</p>
            <button type="button" data-home-celebration-close
                    class="btn mt-5 rounded-xl px-4 py-2 text-sm motion-standard"
                    style="background: var(--surface-sunken); color: var(--text)">{{ setting('celebrations.labels.close', 'تمام') }}</button>
        @else
            <span aria-hidden="true" style="color: var(--color-state-honor)">
                @include('home.partials.icon', ['name' => 'crown', 'size' => 18])
            </span>
            <span>{{ $celebration['message'] }}</span>
            <button type="button" data-home-celebration-close class="opacity-70 hover:opacity-100" aria-label="إغلاق">✕</button>
        @endif
    </div>
</div>

@if (! empty($celebration['sound']) && ! empty($celebration['sound_path']))
    {{-- الصوت يخضع لتوجل الصوت، والأنيميشن حاضر دائمًا (2.14-ب) --}}
    <audio autoplay src="{{ \Illuminate\Support\Facades\Storage::url($celebration['sound_path']) }}"></audio>
@endif

@push('scripts')
    <script>
        (() => {
            const box = document.querySelector('[data-home-celebration]');
            if (!box) return;
            const close = () => box.remove();
            box.addEventListener('click', (e) => {
                if (e.target === box || e.target.closest('[data-home-celebration-close]')) close();
            });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
            setTimeout(close, {{ $seconds * 1000 }});
        })();
    </script>
@endpush
