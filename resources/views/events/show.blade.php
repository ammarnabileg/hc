@extends('layouts.app')
@section('title', $event->title_ar)
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $event->description), 150))
@section('og_image', route('events.og', $event->slug))

@section('content')
    @php
        $user = auth()->user();
        $local = $presenter->localStart($event, $user);
        $localEnd = $presenter->localEnd($event, $user);
        $tz = $presenter->timezone($user);
        $ended = $presenter->hasEnded($event);
        $full = $presenter->isFull($event);
        $seatsLeft = $presenter->seatsLeft($event);
        $free = (float) $event->price_coins <= 0 && (float) $event->price_tickets <= 0;
        $checkinOpen = $attendance->windowOpen($event);
        $fullRewardUntil = $attendance->fullRewardUntil($event);
        $shareText = trim((string) setting('events.share.text', 'شوف الفعاليّة دي معايا:')).' '.route('events.show', $event->slug);
    @endphp

    <x-page-header :title="$event->title_ar"
                   :subtitle="$local->format('Y-m-d · H:i').' — '.$localEnd->format('H:i').' ('.$tz.')'"
                   :breadcrumbs="[
                       ['label' => setting('events.show.breadcrumb_home', 'الرئيسيّة'), 'url' => route('dashboard')],
                       ['label' => setting('events.show.breadcrumb_events', 'الفعاليّات'), 'url' => route('events.index')],
                       ['label' => $event->title_ar],
                   ]">
        <x-slot:action>
            @if ($registration)
                <a href="{{ route('events.ticket', $event->slug) }}"
                   class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">
                    @include('events.components.icon', ['name' => 'ticket']) {{ setting('events.show.my_ticket', 'تذكرتي') }}
                </a>
            @elseif (! $ended && ! $full)
                <a href="#register" class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ setting('events.show.register_now', 'سجّل الآن') }}</a>
            @endif
        </x-slot:action>
    </x-page-header>

    @if (session('error'))
        <x-toast :message="session('error')" state="danger" />
    @endif

    <div class="grid gap-4 lg:grid-cols-3">

        {{-- ------------------------------------------------ العمود الرئيسيّ --}}
        <div class="lg:col-span-2 space-y-4">

            <div class="card p-5 space-y-3">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <x-state-badge :state="$presenter->state($event)" :label="$presenter->stateLabel($event)" />

                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5"
                          style="background: var(--surface-sunken); color: var(--text-muted)">
                        @include('events.components.icon', ['name' => $event->mode])
                        {{ $presenter->modeLabel($event->mode) }}
                    </span>

                    @if ($event->category)
                        <span class="rounded-full px-2 py-0.5" style="background: var(--surface-sunken); color: var(--text-muted)">
                            {{ $event->category }}
                        </span>
                    @endif

                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5"
                          style="background: var(--surface-sunken); color: var(--text-muted)">
                        @include('events.components.icon', ['name' => 'users'])
                        {{ str_replace(':count', (string) $event->registrations_count, (string) setting('events.show.registered_count', ':count مسجَّل'))
                            .($seatsLeft !== null ? ' '.str_replace(':seats', (string) $seatsLeft, (string) setting('events.show.seats_left', '· باقي :seats مكان')) : '') }}
                    </span>
                </div>

                {{-- العدّاد التنازليّ (13.3) --}}
                <div>@include('events.components.countdown', ['event' => $event, 'presenter' => $presenter, 'size' => 'lg'])</div>

                @if ($event->description)
                    <p class="text-sm leading-7" style="color: var(--text-muted)">{{ $event->description }}</p>
                @endif
            </div>

            {{-- الأجندة (event_agenda_items) --}}
            @if ($event->agenda->isNotEmpty())
                <div class="card p-5">
                    <h2 class="font-bold mb-3 flex items-center gap-2">
                        @include('events.components.icon', ['name' => 'clock', 'box' => 18]) {{ setting('events.show.agenda_title', 'الأجندة') }}
                    </h2>
                    <ol class="space-y-3">
                        @foreach ($event->agenda as $item)
                            <li class="flex items-start gap-3">
                                <span class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold"
                                      style="background: var(--surface-sunken); color: var(--color-brand-500)">
                                    {{ $item->starts_at ? \Carbon\CarbonImmutable::parse($item->starts_at)->setTimezone($tz)->format('H:i') : '—' }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold">{{ $item->title }}</span>
                                    @if ($item->speaker)
                                        <span class="block text-xs" style="color: var(--text-muted)">{{ $item->speaker }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            {{-- المتحدّثون --}}
            @if ($speakers->isNotEmpty())
                <div class="card p-5">
                    <h2 class="font-bold mb-3">{{ setting('events.show.speakers_title', 'المتحدّثون') }}</h2>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($speakers as $speaker)
                            <div class="flex items-center gap-2 rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                                <x-avatar :name="$speaker" size="9" />
                                <span class="text-sm">{{ $speaker }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- الخريطة (أوفلاين) أو رابط الانضمام (أونلاين/هجين) — والرابط يظهر قبل الموعد فقط --}}
            <div class="card p-5 space-y-3">
                <h2 class="font-bold flex items-center gap-2">
                    @include('events.components.icon', ['name' => $event->mode === 'offline' ? 'pin' : 'link', 'box' => 18])
                    {{ $event->mode === 'offline' ? setting('events.show.place_title', 'المكان') : setting('events.show.attendance_title', 'الحضور') }}
                </h2>

                @if ($event->mode !== 'online')
                    <p class="text-sm">{{ $event->location ?: setting('events.show.location_tbd', 'المكان يتحدّد قريبًا.') }}</p>
                    @if ($event->lat && $event->lng)
                        <a href="https://www.openstreetmap.org/?mlat={{ $event->lat }}&mlon={{ $event->lng }}#map=16/{{ $event->lat }}/{{ $event->lng }}"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1 text-sm hover:underline" style="color: var(--color-brand-500)">
                            @include('events.components.icon', ['name' => 'pin']) {{ setting('events.show.open_map', 'افتح الخريطة والاتجاهات') }}
                        </a>
                    @endif
                @endif

                @if ($event->mode !== 'offline')
                    @if ($joinLink)
                        <a href="{{ $joinLink }}" target="_blank" rel="noopener"
                           class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                           style="background: var(--color-brand-500); color: #04201c">
                            @include('events.components.icon', ['name' => 'link']) {{ setting('events.show.join_action', 'ادخل الفعاليّة') }}
                        </a>
                    @elseif (! $ended)
                        <p class="text-sm" style="color: var(--text-muted)">
                            {{ str_replace(':at', $presenter->joinLinkOpensAt($event)->setTimezone($tz)->format('Y-m-d · H:i'), (string) setting('events.show.join_link_opens_at', 'رابط الانضمام بيفتح :at')) }}
                            @unless ($registration) {{ setting('events.show.join_link_members_only', '— وبيظهر للمسجَّلين.') }} @endunless
                        </p>
                    @endif
                @endif

                {{-- بعد الانتهاء: رابط التسجيل الخارجيّ (13.3) --}}
                @if ($ended)
                    @if ($event->recording_link)
                        <a href="{{ $event->recording_link }}" target="_blank" rel="noopener"
                           class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @include('events.components.icon', ['name' => 'link']) {{ setting('events.show.recording_action', 'تسجيل الفعاليّة') }}
                        </a>
                    @else
                        <p class="text-sm" style="color: var(--text-muted)">{{ setting('events.show.recording_pending', 'التسجيل هيتنشر هنا أوّل ما يجهز.') }}</p>
                    @endif
                @endif
            </div>
        </div>

        {{-- ------------------------------------------------ العمود الجانبيّ --}}
        <div class="space-y-4">

            {{-- التسجيل: مجّانًا أو بالكوينز/تذكرة --}}
            <div class="card p-5" id="register">
                @if ($registration)
                    @include('events.components.ticket-card', ['registration' => $registration, 'event' => $event, 'presenter' => $presenter])
                @elseif ($ended)
                    <p class="text-sm" style="color: var(--text-muted)">{{ setting('events.show.ended_note', 'الفعاليّة دي خلصت — شوف تسجيلها فوق أو اختار فعاليّة قادمة.') }}</p>
                @elseif ($full)
                    <div class="text-center space-y-2">
                        <x-state-badge state="warn" :label="setting('events.show.full_badge', 'اكتمل العدد')" />
                        <p class="text-sm" style="color: var(--text-muted)">{{ setting('events.show.full_note', 'العدد اكتمل في الفعاليّة دي. تابعنا — بننزل مواعيد جديدة.') }}</p>
                    </div>
                @else
                    <form method="post" action="{{ route('events.register', $event->slug) }}" class="space-y-3">
                        @csrf

                        <div class="text-sm font-bold">
                            {{ $free ? setting('events.show.price_free', 'التسجيل مجّانيّ') : str_replace(':price', trim(
                                ((float) $event->price_coins > 0 ? str_replace(':coins', (string) (int) $event->price_coins, (string) setting('events.show.price_coins', ':coins كوينز')).' ' : '')
                                .((float) $event->price_tickets > 0 ? str_replace(':tickets', (string) (int) $event->price_tickets, (string) setting('events.show.price_tickets', ':tickets تذكرة')) : '')
                            ), (string) setting('events.show.price_paid', 'التسجيل بـ:price')) }}
                        </div>

                        {{-- الهجين: المستخدم يختار نمط الحضور (13.3) --}}
                        @if ($event->mode === 'hybrid')
                            <fieldset class="space-y-2">
                                <legend class="text-xs mb-1" style="color: var(--text-muted)">{{ setting('events.show.attend_mode_legend', 'نمط الحضور') }}</legend>
                                <label class="flex items-center gap-2 text-sm rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                                    <input type="radio" name="attend_mode" value="online" checked> {{ setting('events.show.attend_mode_online', 'أونلاين') }}
                                </label>
                                <label class="flex items-center gap-2 text-sm rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                                    <input type="radio" name="attend_mode" value="offline"> {{ setting('events.show.attend_mode_offline', 'حضور بالمكان') }}
                                </label>
                            </fieldset>
                        @endif

                        @error('attend_mode')
                            <p class="text-xs" style="color: var(--color-state-danger)">{{ $message }}</p>
                        @enderror

                        <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">{{ setting('events.show.register_action', 'سجّل') }}</button>

                        <a href="{{ route('events.ics', $event->slug) }}"
                           class="block text-center text-xs hover:underline" style="color: var(--text-muted)">{{ setting('events.show.add_to_calendar', 'أضِف لتقويمي') }}</a>
                    </form>
                @endif
            </div>

            {{-- الحضور: كود OTP رقميّ (أونلاين) أو تشيك-إن (أوفلاين) — التحقّق خادميّ --}}
            @if ($registration)
                <div class="card p-5 space-y-3" id="checkin">
                    <h2 class="font-bold flex items-center gap-2">
                        @include('events.components.icon', ['name' => 'key', 'box' => 18])
                        {{ ($registration->attend_mode ?? $event->mode) === 'offline' ? setting('events.show.checkin_title', 'تشيك-إن الحضور') : setting('events.show.code_title', 'كود الحضور') }}
                    </h2>

                    @if ($registration->attended)
                        <div class="flex items-center gap-2 text-sm">
                            <x-state-badge state="ok" :label="setting('events.show.attended_badge', 'حضورك مؤكَّد')" />
                            <span style="color: var(--text-muted)">{{ $registration->attended_at?->setTimezone($tz)->format('Y-m-d · H:i') }}</span>
                        </div>

                        @if ($registration->certificate)
                            <div class="rounded-xl p-3 text-sm" style="background: var(--surface-sunken)">
                                @include('events.components.icon', ['name' => 'certificate'])
                                {{ setting('events.show.certificate_ready', 'شهادة الحضور اتفتحت — كودها') }}
                                <span class="font-bold" style="color: var(--color-state-honor)">#{{ $registration->certificate->code }}</span>
                            </div>
                        @else
                            <p class="text-xs" style="color: var(--text-muted)">{{ setting('events.show.certificate_pending', 'استحقاق الشهادة اتسجّل، وهتظهر أوّل ما تُصدَر.') }}</p>
                        @endif
                    @elseif (! $checkinOpen)
                        <p class="text-sm" style="color: var(--text-muted)">
                            {{ setting('events.show.checkin_closed', 'كود الحضور بيفتح مع بداية الفعاليّة. جهّز نفسك — والكود هيتعرض في الفعاليّة نفسها.') }}
                        </p>
                    @else
                        {{--
                          ⭐ **تشيك-إن QR للأوفلاين/الهجين** (13.3 · 12.11):
                          رمزٌ **يخصّ صاحبه** ويتجدّد كلّ 30ث (24.3)، يعرضه
                          المتدرّب فيمسحه المنظِّم بكاميرا هاتفه — فلا تنفع لقطةٌ
                          مرسَلة لغيره، وهو عين «يمنع استخدام كود شخص لآخر».
                          والصورة **SVG من خادمنا** بلا مكتبة ولا أصلٍ خارجيّ.
                        --}}
                        @if ($qrEnabled)
                            <div class="rounded-xl p-3 text-center" style="background: var(--surface-sunken)">
                                <img src="{{ route('events.qr', $event->slug) }}?w={{ time() }}"
                                     alt="{{ setting('events.checkin.qr_alt', 'رمز تشيك-إن الحضور') }}"
                                     width="180" height="180"
                                     class="inline-block max-w-full h-auto rounded-lg"
                                     style="background: #fff"
                                     data-qr-refresh="{{ route('events.qr', $event->slug) }}"
                                     data-qr-seconds="{{ $qrRefreshSeconds }}">
                                <p class="text-xs mt-2" style="color: var(--text-muted)">
                                    {{ setting('events.checkin.qr_hint', 'اعرض الرمز ده للمنظّم عشان يمسحه — بيتجدّد كلّ') }}
                                    {{ $qrRefreshSeconds }} {{ setting('events.checkin.qr_seconds_word', 'ثانية') }}.
                                </p>
                            </div>

                            @push('scripts')
                                <script>
                                    // التجديد الحيّ للرمز — ولو تعطّل الـJS تبقى صورة
                                    // الخادم صالحةً في نافذتها ثمّ يجدّدها إعادة التحميل (2.17-أ)
                                    (function () {
                                        const img = document.querySelector('[data-qr-refresh]');
                                        if (!img) return;
                                        const every = Math.max(5, parseInt(img.dataset.qrSeconds, 10) || 30);
                                        setInterval(function () {
                                            img.src = img.dataset.qrRefresh + '?w=' + Date.now();
                                        }, every * 1000);
                                    })();
                                </script>
                            @endpush
                        @endif

                        <p class="text-xs" style="color: var(--text-muted)">
                            {{ setting('events.show.checkin_hint', 'أدخل الكود المعروض في الفعاليّة — وبيه تتفتح الشهادة والمكافأة.') }}
                        </p>

                        <form method="post" action="{{ route('events.checkin', $event->slug) }}" class="space-y-3">
                            @csrf
                            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                                   maxlength="{{ (int) setting('events.attendance.code_length', 6) }}"
                                   placeholder="{{ str_repeat('•', (int) setting('events.attendance.code_length', 6)) }}"
                                   class="w-full rounded-xl px-3 py-3 text-center text-2xl font-extrabold tracking-[0.5em]"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                                   aria-label="{{ setting('events.show.code_title', 'كود الحضور') }}" required>

                            @error('code')
                                <p class="text-xs" style="color: var(--color-state-danger)">{{ $message }}</p>
                            @enderror

                            <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c">{{ setting('events.show.checkin_action', 'أكّد حضوري') }}</button>
                        </form>
                    @endif

                    {{-- المكافأة متدرّجة زمنيًّا وتُصرَف بالكود فقط (13.3) --}}
                    @if ($reward['xp'] > 0 || $reward['tickets'] > 0)
                        <div class="rounded-xl p-3 text-xs" style="background: var(--surface-sunken); color: var(--text-muted)">
                            @include('events.components.icon', ['name' => 'gift'])
                            {{ setting('events.show.reward_now', 'مكافأة الحضور دلوقتي:') }} <span class="font-bold" style="color: var(--color-brand-500)">{{ str_replace([':xp', ':tickets'], [$reward['xp'], $reward['tickets']], (string) setting('events.show.reward_amount', ':xp XP · :tickets تذكرة')) }}</span>
                            @if ($fullRewardUntil->isFuture())
                                <span class="block mt-1">{{ str_replace(':until', $fullRewardUntil->setTimezone($tz)->format('Y-m-d · H:i'), (string) setting('events.show.reward_full_until', 'المكافأة الكاملة لحدّ :until.')) }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            {{-- دعوة صديق بريفيرال — رابط لهذه الفعاليّة تحديدًا (21.1-ج) --}}
            <div class="card p-5 space-y-3">
                <h2 class="font-bold flex items-center gap-2">
                    @include('events.components.icon', ['name' => 'gift', 'box' => 18]) {{ setting('events.show.invite_title', 'ادعُ صديقك للفعاليّة دي') }}
                </h2>
                <p class="text-xs" style="color: var(--text-muted)">
                    {{ str_replace(':percent', $commissionPercent, (string) setting('events.show.invite_hint', 'صاحبك هيفتح الصفحة دي بالظبط بعد تسجيله — وليه تذكرة ترحيب، وليك :percent% من شحناته.')) }}
                </p>

                <div class="rounded-xl px-3 py-2 text-xs break-all" style="background: var(--surface-sunken)">{{ $inviteLink }}</div>

                <div class="flex flex-wrap gap-2">
                    @include('events.components.copy', ['text' => $inviteLink, 'label' => setting('events.show.copy_invite', 'نسخ رابط الدعوة')])

                    <a href="https://wa.me/?text={{ urlencode($shareText) }}" target="_blank" rel="noopener"
                       class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @include('events.components.icon', ['name' => 'share']) {{ setting('events.show.share_action', 'مشاركة') }}
                    </a>
                </div>

                <a href="{{ route('referral.index') }}" class="block text-xs hover:underline" style="color: var(--color-brand-500)">
                    {{ setting('events.show.all_invites_link', 'كلّ دعواتي وعمولتي') }} ›
                </a>
            </div>
        </div>
    </div>
@endsection

@section('mobile_action')
    @if ($registration)
        <a href="{{ route('events.ticket', $event->slug) }}"
           class="btn w-full flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">{{ setting('events.show.my_ticket', 'تذكرتي') }}</a>
    @elseif (! $presenter->hasEnded($event) && ! $presenter->isFull($event))
        <a href="#register"
           class="btn w-full flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">{{ setting('events.show.register_action', 'سجّل') }}</a>
    @endif
@endsection
