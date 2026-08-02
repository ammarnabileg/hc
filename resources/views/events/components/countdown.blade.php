@php
    /**
     * العدّاد التنازليّ (13.3). قاعدة 2.17-أ الحاسمة:
     * **النصّ الصحيح مرسوم من الخادم**، والـJS يحدّثه فقط — فلا يعلق العدّاد أبدًا.
     */
    $size = $size ?? 'sm';
    $startsAtUtc = \Carbon\CarbonImmutable::parse($event->starts_at)->utc()->format('Y-m-d\TH:i:s\Z');
    $endsAtUtc = \Carbon\CarbonImmutable::parse($presenter->endsAt($event))->utc()->format('Y-m-d\TH:i:s\Z');
@endphp

<span data-countdown="{{ $startsAtUtc }}"
      data-countdown-live="شغّالة دلوقتي"
      data-countdown-ended="انتهت"
      data-countdown-ends="{{ $endsAtUtc }}"
      class="{{ $size === 'lg' ? 'text-lg font-bold' : 'text-xs' }}"
      style="color: {{ $size === 'lg' ? 'var(--color-brand-500)' : 'var(--text-muted)' }}">
    {{ $presenter->countdownText($event) }}
</span>

@once
    @push('scripts')
        <script>
            // يحدّث النصّ كلّ ثانية — وأيّ خطأ يترك نصّ الخادم كما هو (2.17-أ)
            (function () {
                const nodes = document.querySelectorAll('[data-countdown]');
                if (!nodes.length) return;

                const unit = (n, one, two, few) => n === 1 ? one : (n === 2 ? two : (n <= 10 ? n + ' ' + few : n + ' ' + one));

                const render = (el) => {
                    const start = Date.parse(el.dataset.countdown);
                    const end = Date.parse(el.dataset.countdownEnds);
                    if (!Number.isFinite(start)) return;

                    const now = Date.now();
                    if (Number.isFinite(end) && now >= end) { el.textContent = el.dataset.countdownEnded; return; }
                    if (now >= start) { el.textContent = el.dataset.countdownLive; return; }

                    let left = Math.floor((start - now) / 1000);
                    const d = Math.floor(left / 86400); left -= d * 86400;
                    const h = Math.floor(left / 3600); left -= h * 3600;
                    const m = Math.floor(left / 60);
                    const s = left - m * 60;

                    const parts = [];
                    if (d) parts.push(unit(d, 'يوم', 'يومين', 'أيّام'));
                    if (d || h) parts.push(unit(h, 'ساعة', 'ساعتين', 'ساعات'));
                    if (!d) parts.push(unit(m, 'دقيقة', 'دقيقتين', 'دقايق'));
                    if (!d && !h) parts.push(unit(s, 'ثانية', 'ثانيتين', 'ثوانٍ'));

                    el.textContent = 'باقي ' + parts.join(' و');
                };

                const tick = () => nodes.forEach((el) => { try { render(el); } catch (e) { /* نصّ الخادم يبقى كما هو */ } });
                tick();
                setInterval(tick, 1000);
            })();
        </script>
    @endpush
@endonce
