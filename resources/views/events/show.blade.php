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
                       ['label' => 'الرئيسيّة', 'url' => route('dashboard')],
                       ['label' => 'الفعاليّات', 'url' => route('events.index')],
                       ['label' => $event->title_ar],
                   ]">
        <x-slot:action>
            @if ($registration)
                <a href="{{ route('events.ticket', $event->slug) }}"
                   class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">
                    @include('events.components.icon', ['name' => 'ticket']) تذكرتي
                </a>
            @elseif (! $ended && ! $full)
                <a href="#register" class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">سجّل الآن</a>
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
                        {{ $event->registrations_count }} مسجَّل{{ $seatsLeft !== null ? ' · باقي '.$seatsLeft.' مكان' : '' }}
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
                        @include('events.components.icon', ['name' => 'clock', 'box' => 18]) الأجندة
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
                    <h2 class="font-bold mb-3">المتحدّثون</h2>
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
                    {{ $event->mode === 'offline' ? 'المكان' : 'الحضور' }}
                </h2>

                @if ($event->mode !== 'online')
                    <p class="text-sm">{{ $event->location ?: 'المكان يتحدّد قريبًا.' }}</p>
                    @if ($event->lat && $event->lng)
                        <a href="https://www.openstreetmap.org/?mlat={{ $event->lat }}&mlon={{ $event->lng }}#map=16/{{ $event->lat }}/{{ $event->lng }}"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1 text-sm hover:underline" style="color: var(--color-brand-500)">
                            @include('events.components.icon', ['name' => 'pin']) افتح الخريطة والاتجاهات
                        </a>
                    @endif
                @endif

                @if ($event->mode !== 'offline')
                    @if ($joinLink)
                        <a href="{{ $joinLink }}" target="_blank" rel="noopener"
                           class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                           style="background: var(--color-brand-500); color: #04201c">
                            @include('events.components.icon', ['name' => 'link']) ادخل الفعاليّة
                        </a>
                    @elseif (! $ended)
                        <p class="text-sm" style="color: var(--text-muted)">
                            رابط الانضمام بيفتح
                            {{ $presenter->joinLinkOpensAt($event)->setTimezone($tz)->format('Y-m-d · H:i') }}
                            @unless ($registration) — وبيظهر للمسجَّلين. @endunless
                        </p>
                    @endif
                @endif

                {{-- بعد الانتهاء: رابط التسجيل الخارجيّ (13.3) --}}
                @if ($ended)
                    @if ($event->recording_link)
                        <a href="{{ $event->recording_link }}" target="_blank" rel="noopener"
                           class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @include('events.components.icon', ['name' => 'link']) تسجيل الفعاليّة
                        </a>
                    @else
                        <p class="text-sm" style="color: var(--text-muted)">التسجيل هيتنشر هنا أوّل ما يجهز.</p>
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
                    <p class="text-sm" style="color: var(--text-muted)">الفعاليّة دي خلصت — شوف تسجيلها فوق أو اختار فعاليّة قادمة.</p>
                @elseif ($full)
                    <div class="text-center space-y-2">
                        <x-state-badge state="warn" label="اكتمل العدد" />
                        <p class="text-sm" style="color: var(--text-muted)">العدد اكتمل في الفعاليّة دي. تابعنا — بننزل مواعيد جديدة.</p>
                    </div>
                @else
                    <form method="post" action="{{ route('events.register', $event->slug) }}" class="space-y-3">
                        @csrf

                        <div class="text-sm font-bold">
                            {{ $free ? 'التسجيل مجّانيّ' : 'التسجيل بـ'.trim(((float) $event->price_coins > 0 ? (int) $event->price_coins.' كوينز ' : '').((float) $event->price_tickets > 0 ? (int) $event->price_tickets.' تذكرة' : '')) }}
                        </div>

                        {{-- الهجين: المستخدم يختار نمط الحضور (13.3) --}}
                        @if ($event->mode === 'hybrid')
                            <fieldset class="space-y-2">
                                <legend class="text-xs mb-1" style="color: var(--text-muted)">نمط الحضور</legend>
                                <label class="flex items-center gap-2 text-sm rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                                    <input type="radio" name="attend_mode" value="online" checked> أونلاين
                                </label>
                                <label class="flex items-center gap-2 text-sm rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                                    <input type="radio" name="attend_mode" value="offline"> حضور بالمكان
                                </label>
                            </fieldset>
                        @endif

                        @error('attend_mode')
                            <p class="text-xs" style="color: var(--color-state-danger)">{{ $message }}</p>
                        @enderror

                        <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">سجّل</button>

                        <a href="{{ route('events.ics', $event->slug) }}"
                           class="block text-center text-xs hover:underline" style="color: var(--text-muted)">أضِف لتقويمي</a>
                    </form>
                @endif
            </div>

            {{-- الحضور: كود OTP رقميّ (أونلاين) أو تشيك-إن (أوفلاين) — التحقّق خادميّ --}}
            @if ($registration)
                <div class="card p-5 space-y-3" id="checkin">
                    <h2 class="font-bold flex items-center gap-2">
                        @include('events.components.icon', ['name' => 'key', 'box' => 18])
                        {{ ($registration->attend_mode ?? $event->mode) === 'offline' ? 'تشيك-إن الحضور' : 'كود الحضور' }}
                    </h2>

                    @if ($registration->attended)
                        <div class="flex items-center gap-2 text-sm">
                            <x-state-badge state="ok" label="حضورك مؤكَّد" />
                            <span style="color: var(--text-muted)">{{ $registration->attended_at?->setTimezone($tz)->format('Y-m-d · H:i') }}</span>
                        </div>

                        @if ($registration->certificate)
                            <div class="rounded-xl p-3 text-sm" style="background: var(--surface-sunken)">
                                @include('events.components.icon', ['name' => 'certificate'])
                                شهادة الحضور اتفتحت — كودها
                                <span class="font-bold" style="color: var(--color-state-honor)">#{{ $registration->certificate->code }}</span>
                            </div>
                        @else
                            <p class="text-xs" style="color: var(--text-muted)">استحقاق الشهادة اتسجّل، وهتظهر أوّل ما تُصدَر.</p>
                        @endif
                    @elseif (! $checkinOpen)
                        <p class="text-sm" style="color: var(--text-muted)">
                            كود الحضور بيفتح مع بداية الفعاليّة. جهّز نفسك — والكود هيتعرض في الفعاليّة نفسها.
                        </p>
                    @else
                        <p class="text-xs" style="color: var(--text-muted)">
                            أدخل الكود المعروض في الفعاليّة — وبيه تتفتح الشهادة والمكافأة.
                        </p>

                        <form method="post" action="{{ route('events.checkin', $event->slug) }}" class="space-y-3">
                            @csrf
                            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                                   maxlength="{{ (int) setting('events.attendance.code_length', 6) }}"
                                   placeholder="{{ str_repeat('•', (int) setting('events.attendance.code_length', 6)) }}"
                                   class="w-full rounded-xl px-3 py-3 text-center text-2xl font-extrabold tracking-[0.5em]"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                                   aria-label="كود الحضور" required>

                            @error('code')
                                <p class="text-xs" style="color: var(--color-state-danger)">{{ $message }}</p>
                            @enderror

                            <button type="submit" class="btn w-full rounded-xl px-4 py-3 text-sm font-bold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c">أكّد حضوري</button>
                        </form>
                    @endif

                    {{-- المكافأة متدرّجة زمنيًّا وتُصرَف بالكود فقط (13.3) --}}
                    @if ($reward['xp'] > 0 || $reward['tickets'] > 0)
                        <div class="rounded-xl p-3 text-xs" style="background: var(--surface-sunken); color: var(--text-muted)">
                            @include('events.components.icon', ['name' => 'gift'])
                            مكافأة الحضور دلوقتي: <span class="font-bold" style="color: var(--color-brand-500)">{{ $reward['xp'] }} XP · {{ $reward['tickets'] }} تذكرة</span>
                            @if ($fullRewardUntil->isFuture())
                                <span class="block mt-1">المكافأة الكاملة لحدّ {{ $fullRewardUntil->setTimezone($tz)->format('Y-m-d · H:i') }}.</span>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            {{-- دعوة صديق بريفيرال — رابط لهذه الفعاليّة تحديدًا (21.1-ج) --}}
            <div class="card p-5 space-y-3">
                <h2 class="font-bold flex items-center gap-2">
                    @include('events.components.icon', ['name' => 'gift', 'box' => 18]) ادعُ صديقك للفعاليّة دي
                </h2>
                <p class="text-xs" style="color: var(--text-muted)">
                    صاحبك هيفتح الصفحة دي بالظبط بعد تسجيله — وليه تذكرة ترحيب، وليك {{ $commissionPercent }}% من شحناته.
                </p>

                <div class="rounded-xl px-3 py-2 text-xs break-all" style="background: var(--surface-sunken)">{{ $inviteLink }}</div>

                <div class="flex flex-wrap gap-2">
                    @include('events.components.copy', ['text' => $inviteLink, 'label' => 'نسخ رابط الدعوة'])

                    <a href="https://wa.me/?text={{ urlencode($shareText) }}" target="_blank" rel="noopener"
                       class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @include('events.components.icon', ['name' => 'share']) مشاركة
                    </a>
                </div>

                <a href="{{ route('referral.index') }}" class="block text-xs hover:underline" style="color: var(--color-brand-500)">
                    كلّ دعواتي وعمولتي ›
                </a>
            </div>
        </div>
    </div>
@endsection

@section('mobile_action')
    @if ($registration)
        <a href="{{ route('events.ticket', $event->slug) }}"
           class="btn w-full flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">تذكرتي</a>
    @elseif (! $presenter->hasEnded($event) && ! $presenter->isFull($event))
        <a href="#register"
           class="btn w-full flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">سجّل</a>
    @endif
@endsection
