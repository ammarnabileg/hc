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
      data-countdown-live="{{ setting('events.countdown.live', 'شغّالة دلوقتي') }}"
      data-countdown-ended="{{ setting('events.countdown.ended', 'انتهت') }}"
      data-countdown-ends="{{ $endsAtUtc }}"
      class="{{ $size === 'lg' ? 'text-lg font-bold' : 'text-xs' }}"
      style="color: {{ $size === 'lg' ? 'var(--color-brand-500)' : 'var(--text-muted)' }}">
    {{ $presenter->countdownText($event) }}
</span>

@once
    @push('scripts')
        @php
            // ألفاظ العدّاد من الإعدادات لا من السكربت (2.13) — وصيغ الجمع
            // العربيّة: مفرد · مثنّى · جمع قلّة (3–10) · وما فوقها يعود للمفرد،
            // و`:n` مكان الرقم.
            $countdownUnits = [
                'days' => [
                    'one' => (string) setting('events.countdown.days_one', 'يوم'),
                    'two' => (string) setting('events.countdown.days_two', 'يومين'),
                    'few' => (string) setting('events.countdown.days_few', ':n أيّام'),
                    'many' => (string) setting('events.countdown.days_many', ':n يوم'),
                ],
                'hours' => [
                    'one' => (string) setting('events.countdown.hours_one', 'ساعة'),
                    'two' => (string) setting('events.countdown.hours_two', 'ساعتين'),
                    'few' => (string) setting('events.countdown.hours_few', ':n ساعات'),
                    'many' => (string) setting('events.countdown.hours_many', ':n ساعة'),
                ],
                'minutes' => [
                    'one' => (string) setting('events.countdown.minutes_one', 'دقيقة'),
                    'two' => (string) setting('events.countdown.minutes_two', 'دقيقتين'),
                    'few' => (string) setting('events.countdown.minutes_few', ':n دقايق'),
                    'many' => (string) setting('events.countdown.minutes_many', ':n دقيقة'),
                ],
                'seconds' => [
                    'one' => (string) setting('events.countdown.seconds_one', 'ثانية'),
                    'two' => (string) setting('events.countdown.seconds_two', 'ثانيتين'),
                    'few' => (string) setting('events.countdown.seconds_few', ':n ثوانٍ'),
                    'many' => (string) setting('events.countdown.seconds_many', ':n ثانية'),
                ],
                'remaining' => (string) setting('events.countdown.remaining', 'باقي :parts'),
                'joiner' => (string) setting('events.countdown.joiner', ' و'),
            ];
        @endphp
        <script>
            // يحدّث النصّ كلّ ثانية — وأيّ خطأ يترك نصّ الخادم كما هو (2.17-أ)
            (function () {
                const nodes = document.querySelectorAll('[data-countdown]');
                if (!nodes.length) return;

                const units = @json($countdownUnits);
                const unit = (n, forms) =>
                    (n === 1 ? forms.one : (n === 2 ? forms.two : (n <= 10 ? forms.few : forms.many)))
                        .split(':n').join(n);

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
                    if (d) parts.push(unit(d, units.days));
                    if (d || h) parts.push(unit(h, units.hours));
                    if (!d) parts.push(unit(m, units.minutes));
                    if (!d && !h) parts.push(unit(s, units.seconds));

                    el.textContent = units.remaining.split(':parts').join(parts.join(units.joiner));
                };

                const tick = () => nodes.forEach((el) => { try { render(el); } catch (e) { /* نصّ الخادم يبقى كما هو */ } });
                tick();
                setInterval(tick, 1000);
            })();
        </script>
    @endpush
@endonce
