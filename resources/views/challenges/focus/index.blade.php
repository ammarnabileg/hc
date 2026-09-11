@extends('layouts.app')
@section('title', setting('challenges.focus.title', 'حرب التركيز'))

@section('content')
    <x-page-header
        :title="setting('challenges.focus.title', 'حرب التركيز')"
        :subtitle="setting('challenges.focus.subtitle', 'عمل عميق بلا مقاطعة — والمكافأة دقائق تركيز مش تذاكر.')"
        :breadcrumbs="[['label' => setting('challenges.index.title', 'التحديات'), 'url' => route('challenges.index')], ['label' => setting('challenges.focus.title', 'حرب التركيز')]]">
        <x-slot:action>
            <button type="button" data-modal-open="focus-new"
                    class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('challenges.focus.new_action', 'تحدّي جديد') }}</button>
        </x-slot:action>
    </x-page-header>

    {{-- ⭐ عدّاد جلسة التركيز الحيّ — «إظهار الدقائق وهي بتكبر» ويكمل حتى لو قفل الشاشة (15.3) --}}
    {{-- ولحظاته من الخادم (`started_at`/`ends_at`) لا من عدّادٍ يعيش في المتصفّح،
         فـ«العدّاد يكمل حتى لو قفل الشاشة أو خرج من التبويب» (15.3-ب): الـReload
         يرسم الرقم الصحيح فورًا لأنّه محسوبٌ من فارق لحظتين لا من عدٍّ متراكم. --}}
    @foreach ($sessions as $session)
        @php
            $remainingText = sprintf('%02d:%02d', intdiv($session['remaining_seconds'], 60), $session['remaining_seconds'] % 60);
        @endphp

        <section class="card p-5 mb-5 animate-fadeup"
                 data-focus-timer
                 data-focus-started="{{ $session['started_at'] }}"
                 data-focus-ends="{{ $session['ends_at'] }}"
                 data-focus-server-now="{{ $session['server_now'] }}"
                 data-focus-duration="{{ $session['duration_minutes'] }}"
                 style="border: 1px solid var(--color-brand-500)">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="font-bold text-sm" style="color: var(--color-brand-400)">
                    {{ setting('challenges.focus.live_title', 'جلسة تركيز جارية') }}
                </h2>
                <span class="text-sm font-mono" data-focus-remaining
                      style="color: var(--text-muted)">{{ $remainingText }}</span>
            </div>

            {{-- الرقم الكبير: الدقائق وهي بتكبر — والنفور من الخسارة محرّكها (15.3) --}}
            <div class="flex items-end gap-2 mb-3">
                <span class="text-5xl font-black leading-none" data-focus-elapsed
                      style="color: var(--color-brand-500)">{{ $session['elapsed_minutes'] }}</span>
                <span class="text-xs pb-1" style="color: var(--text-muted)">
                    {{ str_replace(':total', (string) $session['duration_minutes'], (string) setting('challenges.focus.live_of_total', 'دقيقة من :total')) }}
                </span>
            </div>

            <div class="w-full rounded-full overflow-hidden" style="height: 8px; background: var(--surface-sunken)"
                 role="progressbar" aria-valuemin="0" aria-valuemax="100"
                 aria-valuenow="{{ $session['percent'] }}"
                 aria-label="{{ setting('challenges.focus.live_progress_label', 'تقدّم جلسة التركيز') }}">
                <div class="h-full motion-standard" data-focus-bar
                     style="width: {{ $session['percent'] }}%; background: var(--color-brand-500)"></div>
            </div>

            @if ($session['intention'])
                <p class="text-xs mt-3" style="color: var(--text-muted)">
                    {{ str_replace(':intention', (string) $session['intention'], (string) setting('challenges.focus.live_intention', 'نيّتك: :intention')) }}
                </p>
            @endif

            <p class="text-xs mt-2" style="color: var(--text-muted)">
                {{ setting('challenges.focus.live_note', 'العدّاد ماشي على ساعة السيرفر — بيكمل حتى لو قفلت الشاشة أو خرجت من التبويب.') }}
            </p>

            <p class="text-xs mt-2 font-bold" data-focus-done hidden style="color: var(--color-brand-400)">
                {{ setting('challenges.focus.live_done', 'خلصت المدّة — دقائق تركيزك اتسجّلت ✓') }}
            </p>
        </section>
    @endforeach

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-kpi :label="setting('challenges.focus.kpi_minutes', 'دقائق تركيزي')" :value="$focusMinutes" icon="shield" />
        <x-kpi :label="setting('challenges.focus.kpi_active', 'تحدّياتي النشطة')" :value="$activeOwned.' / '.$maxActive" icon="placement" />
        <x-kpi :label="setting('challenges.focus.kpi_create_cost', 'تكلفة الإنشاء')" :value="(int) $createCost" icon="ticket" />
        <x-kpi :label="setting('challenges.focus.kpi_tickets', 'تذاكري')" :value="(int) $ticketsBalance" icon="card" />
    </div>

    {{-- رسالة الأمانة — قلب هذه الحرب (15.3) --}}
    <blockquote class="card p-4 mb-5 text-sm leading-relaxed" style="border-inline-start: 3px solid var(--color-brand-500)">
        {{ $honesty }}
    </blockquote>

    @if ($board->isEmpty())
        <x-empty :message="setting('challenges.focus.empty', 'مفيش تحدّيات تركيز نشطة — ابدأ إنت أوّل واحد.')" />
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($board as $row)
                @php
                    $war = $row['war'];
                    $joiners = $row['joiners'];
                    $extra = max(0, $joiners->count() - 4);
                @endphp

                <article class="card p-4 flex flex-col gap-3 animate-fadeup">
                    <div class="flex items-start justify-between gap-3">
                        <span style="color: var(--color-brand-400)">
                            @include('challenges.components.war-icon', ['type' => 'focus', 'size' => 36, 'label' => setting('challenges.focus.icon_label', 'حرب تركيز')])
                        </span>
                        <x-state-badge :state="$war->is_group ? 'ok' : 'idle'"
                                       :label="$war->is_group ? setting('challenges.focus.badge_group', 'جماعيّ') : setting('challenges.focus.badge_solo', 'فرديّ')" />
                    </div>

                    <div>
                        <h2 class="font-bold">{{ str_replace(':minutes', $war->duration_minutes, (string) setting('challenges.focus.card_duration', ':minutes دقيقة تركيز')) }}</h2>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ $war->intention ?: setting('challenges.focus.no_intention', 'بلا نيّة مكتوبة') }} {{ str_replace(':owner', (string) $war->owner?->name, (string) setting('challenges.focus.card_owner', '· صاحبه: :owner')) }}
                        </p>
                    </div>

                    {{-- أكوام الأفاتار: دليل اجتماعيّ يشجّع على الانضمام (15.3) --}}
                    @if ($joiners->isNotEmpty())
                        <div class="flex items-center gap-2">
                            <div class="flex items-center">
                                @foreach ($joiners->take((int) setting('wars.focus.avatars_shown', 4)) as $member)
                                    <span class="inline-flex" style="margin-inline-start: {{ $loop->first ? 0 : '-0.6rem' }}">
                                        <x-avatar :user="$member->user" size="10" />
                                    </span>
                                @endforeach
                            </div>
                            @if ($extra > 0)
                                <span class="text-xs font-bold" style="color: var(--text-muted)">+{{ $extra }}</span>
                            @endif
                            <span class="text-xs" style="color: var(--text-muted)">{{ setting('challenges.focus.joiners_label', 'منضمّين معاه') }}</span>
                        </div>
                    @endif

                    <div class="mt-auto pt-1 flex flex-wrap gap-2">
                        @if ($row['mine'])
                            <form method="post" action="{{ route('challenges.focus.cancel', $war) }}" class="flex-1">
                                @csrf
                                <button type="submit"
                                        class="w-full rounded-xl px-4 py-2.5 text-sm motion-standard"
                                        style="background: var(--surface-sunken); color: var(--text); border: 1px solid var(--border); min-height: 44px">
                                    {{ setting('challenges.focus.cancel_action', 'ألغِ التحدّي') }}
                                </button>
                            </form>
                        @elseif ($row['joined'])
                            <span class="flex-1 text-center text-xs py-3" style="color: var(--text-muted)">{{ setting('challenges.focus.joined_note', 'إنت منضمّ — ركّز') }} <x-icon name="shield" size="16" /></span>
                        @elseif ($war->is_group)
                            <form method="post" action="{{ route('challenges.focus.join', $war) }}" class="flex-1">
                                @csrf
                                <button type="submit"
                                        class="btn w-full rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                                        style="background: var(--color-brand-500); color: #04201c; min-height: 44px">
                                    {{ str_replace(':cost', (int) $joinCost, (string) setting('challenges.focus.join_action', 'انضمّ بـ:cost تذكرة')) }}
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @push('modals')
        <x-modal id="focus-new" :title="setting('challenges.focus.modal_title', 'تحدّي تركيز جديد')">
            <form method="post" action="{{ route('challenges.focus.store') }}" class="space-y-4 text-sm" id="focus-new-form">
                @csrf

                <fieldset>
                    <legend class="font-bold mb-2">{{ setting('challenges.focus.duration_legend', 'المدّة') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($durations as $minutes)
                            <label class="cursor-pointer">
                                <input type="radio" name="duration_minutes" value="{{ $minutes }}"
                                       class="sr-only peer" @checked($loop->first)>
                                <span class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm motion-standard
                                             peer-checked:font-bold"
                                      style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    {{ str_replace(':minutes', $minutes, (string) setting('challenges.focus.duration_option', ':minutes دقيقة')) }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('challenges.focus.intention_label', 'نيّتك (اختياريّ)') }}</span>
                    <input type="text" name="intention" maxlength="240" placeholder="{{ setting('challenges.focus.intention_placeholder', 'أقرأ كتاب كذا · أخلّص مهمّة كذا') }}"
                           class="w-full rounded-xl px-3 py-3 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_group" value="1" class="w-5 h-5">
                    <span>{{ setting('challenges.focus.group_toggle', 'خلّيه تحدّيًا جماعيًّا — الناس تقدر تنضمّ بتذكرة تروح لك.') }}</span>
                </label>

                <p class="text-xs" style="color: var(--text-muted)">
                    {{ str_replace([':create', ':join'], [(int) $createCost, (int) $joinCost], (string) setting('challenges.focus.economy_note', 'الإنشاء بـ:create تذاكر وغير قابلة للاسترجاع، وكلّ منضمّ بيدّيك :join تذكرة — يعني تحدّي حلو الناس تحبّه = مكسب.')) }}
                </p>
            </form>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-xl px-4 py-2.5 text-sm motion-standard"
                            style="background: var(--surface-sunken); color: var(--text); min-height: 44px">{{ setting('challenges.focus.modal_cancel', 'مش دلوقتي') }}</button>
                    <button type="submit" form="focus-new-form"
                            class="btn rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c; min-height: 44px">{{ setting('challenges.focus.modal_submit', 'ابدأ التحدّي') }}</button>
                </div>
            </x-slot:footer>
        </x-modal>
    @endpush

    @if ($sessions->isNotEmpty())
        @push('scripts')
            <script>
                /*
                 | عدّاد حرب التركيز (15.3).
                 |
                 | ⭐ **السلطة للخادم لا للمتصفّح:** لا عدَّ تنازليًّا متراكمًا هنا — كلّ
                 | نبضة تُعيد الحساب من `started_at`/`ends_at` المرسومَين من الخادم،
                 | مصحّحَين بفارق الساعتين (`server_now` − ساعة المتصفّح لحظة الرسم).
                 | فلو نامت الشاشة أو غاب التبويب دقائق، أوّل نبضة بعد العودة تقفز
                 | للرقم الصحيح بدل أن تستأنف من حيث توقّفت — وهذا عين ما يشترطه
                 | القرار (ب): «العدّاد يكمل حتى لو قفل الشاشة».
                 |
                 | و**النصّ الصحيح مرسوم من الخادم أصلًا** (2.17-أ)، فلو تعطّل السكربت
                 | بقي الرقم صحيحًا لحظة الفتح ولا يعلق العدّاد فارغًا أبدًا.
                 */
                (function () {
                    const cards = document.querySelectorAll('[data-focus-timer]');
                    if (!cards.length) return;

                    const statusUrl = @json(route('challenges.focus.status'));
                    const pad = (n) => String(n).padStart(2, '0');

                    // فارق ساعة المتصفّح عن ساعة الخادم — يُطرَح من كلّ قراءة
                    let skew = 0;
                    const firstNow = Date.parse(cards[0].dataset.focusServerNow);
                    if (Number.isFinite(firstNow)) skew = Date.now() - firstNow;

                    const serverNow = () => Date.now() - skew;

                    let settling = false;

                    const render = (card) => {
                        const start = Date.parse(card.dataset.focusStarted);
                        const end = Date.parse(card.dataset.focusEnds);
                        if (!Number.isFinite(start) || !Number.isFinite(end)) return false;

                        const total = Math.max(0, Math.round((end - start) / 1000));
                        if (!total) return false;

                        const elapsed = Math.min(total, Math.max(0, Math.floor((serverNow() - start) / 1000)));
                        const left = total - elapsed;

                        const minutes = card.querySelector('[data-focus-elapsed]');
                        const remaining = card.querySelector('[data-focus-remaining]');
                        const bar = card.querySelector('[data-focus-bar]');
                        const done = card.querySelector('[data-focus-done]');
                        const meter = card.querySelector('[role="progressbar"]');
                        const percent = Math.min(100, Math.floor((elapsed * 100) / total));

                        if (minutes) minutes.textContent = Math.floor(elapsed / 60);
                        if (remaining) remaining.textContent = pad(Math.floor(left / 60)) + ':' + pad(left % 60);
                        if (bar) bar.style.width = percent + '%';
                        if (meter) meter.setAttribute('aria-valuenow', percent);
                        if (done && left <= 0) done.hidden = false;

                        return left <= 0;
                    };

                    // انقضت المدّة ⟵ نسأل الخادم ليسجّل الدقائق ويفتح الشارة، ثمّ نعرض الحصيلة
                    const settle = () => {
                        if (settling) return;
                        settling = true;

                        fetch(statusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                            .then(() => window.location.reload())
                            .catch(() => { settling = false; });
                    };

                    const tick = () => {
                        let finished = false;

                        cards.forEach((card) => {
                            try { finished = render(card) || finished; } catch (e) { /* نصّ الخادم يبقى كما هو */ }
                        });

                        if (finished) settle();
                    };

                    tick();
                    setInterval(tick, 1000);

                    // العودة من قفل الشاشة: نبضة فوريّة بدل انتظار الثانية التالية
                    document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
                })();
            </script>
        @endpush
    @endif
@endsection

@section('mobile_action')
    <button type="button" data-modal-open="focus-new"
            class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
            style="background: var(--color-brand-500); color: #04201c">{{ setting('challenges.focus.modal_title', 'تحدّي تركيز جديد') }}</button>
@endsection
