@extends('layouts.app')
@section('title', (string) setting('referral.index.section_1', 'ادعُ أصدقاءك'))
@section('meta_description', (string) setting('referral.index.section_2', 'ادعُ أصدقاءك واكسب عمولة على شحناتهم — ولصاحبك تذكرة ترحيب.'))

@section('content')
    @php
        $shareText = trim((string) setting('referral.share.text', 'انضمّ معايا على المنصّة — هتلاقي تدريبات وشهادات حقيقيّة:')).' '.$link;
        $welcomeTickets = (int) setting('referral.welcome_tickets', 1);
    @endphp

    <x-page-header title="{{ setting('referral.index.title_1', 'ادعُ أصدقاءك') }}"
                   subtitle="{{ setting('referral.index.subtitle_1', 'كلّ صاحب تجيبه ليه تذكرة ترحيب، وليك عمولة على شحناته.') }}"
                   :breadcrumbs="[['label' => (string) setting('referral.index.breadcrumbs_1', 'الرئيسيّة'), 'url' => route('dashboard')], ['label' => (string) setting('referral.index.breadcrumbs_2', 'ادعُ أصدقاءك')]]">
        <x-slot:action>
            @include('events.components.copy', ['text' => $link, 'label' => (string) setting('referral.index.include_1', 'نسخ رابط الدعوة')])
        </x-slot:action>
    </x-page-header>

    {{-- الصفحة اللي اتدعيت ليها تُفتَح لك بعد التسجيل (21.1-ج) --}}
    @if ($landingUrl)
        <div class="card p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
            <span class="text-sm">{{ strtr((string) setting('referral.index.text_1', 'اتدعيت لـ«:page» — نكمّل من هناك؟'), [':page' => (string) ($landingLabel ?? setting('referral.index.expr_1', 'صفحة معيّنة'))]) }}</span>
            <a href="{{ $landingUrl }}" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">{{ setting('referral.index.text_3', 'افتح الصفحة') }}</a>
        </div>
    @endif

    {{--
        ⭐ الهيرو (7.6.2): شارة «عمولة مدى الحياة» + **رقم 7% ضخم** + العنوان
        والنصّ النفسيّ. كان المطبَّق سطرًا صغيرًا داخل كارت الرابط، والرقم الذي
        يُفترَض أن يكون **بطل الصفحة** لا يُقرأ إن كان بحجم نصّ عاديّ.
    --}}
    <section class="card p-6 mb-4 text-center overflow-hidden relative">
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
              style="background: color-mix(in srgb, var(--color-state-honor) 16%, transparent); color: var(--color-state-honor)">
            <x-icon name="crown" size="14" />
            {{ setting('referral.hero.badge', 'عمولة مدى الحياة') }}
        </span>

        <div class="mt-3 font-extrabold leading-none tabular-nums"
             style="font-size: clamp(3.5rem, 14vw, 6rem); color: var(--color-brand-500)">
            {{ $stats['percent'] }}%
        </div>

        <h2 class="mt-2 text-lg font-bold">
            {{ str_replace(':percent', (string) $stats['percent'], (string) setting('referral.hero.title', ':percent% من إجماليّ شحن كلّ من دعوتهم — مدى الحياة')) }}
        </h2>

        <p class="mt-2 text-sm max-w-xl mx-auto" style="color: var(--text-muted)">
            {{ setting('referral.hero.note') }}
        </p>
    </section>

    {{-- ⭐ الكارت الواحد البارز: الرابط + العدّاد + العمولة + المشاركة (24.5) --}}
    <div class="card p-5 mb-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="text-xs mb-1" style="color: var(--text-muted)">{{ setting('referral.index.text_4', 'رابط دعوتك الخاصّ') }}</div>
                <div class="rounded-xl px-3 py-2 text-sm break-all" style="background: var(--surface-sunken)">{{ $link }}</div>

                <div class="flex flex-wrap gap-2 mt-3">
                    @include('events.components.copy', ['text' => $link, 'label' => (string) setting('referral.index.include_2', 'نسخ')])

                    <a href="https://wa.me/?text={{ urlencode($shareText) }}" target="_blank" rel="noopener"
                       class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @include('events.components.icon', ['name' => 'share']) {{ setting('referral.index.text_5', 'واتساب') }}
                    </a>

                    {{-- ⭐ فيسبوك كان غائبًا من قائمة المشاركة رغم نصّ 7.6.2 --}}
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($link) }}"
                       target="_blank" rel="noopener"
                       class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @include('events.components.icon', ['name' => 'share']) {{ setting('referral.share.facebook_label', 'فيسبوك') }}
                    </a>

                    <a href="https://t.me/share/url?url={{ urlencode($link) }}&text={{ urlencode((string) setting('referral.share.text', 'انضمّ معايا على المنصّة')) }}"
                       target="_blank" rel="noopener"
                       class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @include('events.components.icon', ['name' => 'share']) {{ setting('referral.share.telegram_label', 'تيليجرام') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid gap-3 grid-cols-2 lg:grid-cols-4 mb-4">
        <x-kpi label="{{ setting('referral.index.label_1', 'المدعوّون') }}" :value="$stats['invited']" icon="people" />
        <x-kpi label="{{ setting('referral.index.label_2', 'أتمّوا التفعيل') }}" :value="$stats['completed']" icon="check" />
        <x-kpi label="{{ setting('referral.index.label_3', 'في الانتظار') }}" :value="$stats['pending']" icon="hourglass" />
        <x-kpi label="{{ setting('referral.index.label_4', 'العمولة المكتسبة') }}" :value="$stats['commission']" icon="money"
               :hint="strtr((string) setting('referral.index.hint_1', 'نسبتك :a1% مدى الحياة'), [':a1' => (string) ($stats['percent'])])" />
    </div>

    {{-- ⭐ الآلة الحاسبة التفاعليّة — «قلب التفاعل» (7.6.2) --}}
    @include('referral.partials.calculator', ['calculator' => $calculator])

    {{-- ⭐ «كيف يعمل؟» بأربع خطوات (7.6.2) --}}
    @include('referral.partials.how-it-works')

    {{-- ⭐ «شبكتي» عرضًا بصريًّا وهي بتنمو (7.6.1 · 2.9-8) --}}
    @include('referral.partials.network', ['ambassador' => $ambassador, 'invited' => $invited])

    {{-- ⭐ رابط دعوة لكلّ محتوى (Deep link) — 21.1-ج --}}
    @if ($deepLinks)
        <div class="card p-5 mb-4">
            <h2 class="font-bold mb-1">{{ setting('referral.index.text_6', 'ادعُ صديقك لمحتوى بعينه') }}</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted)">
                {{ strtr((string) setting('referral.index.text_7', 'الرابط ده بيفتح الصفحة نفسها لصاحبك بعد ما يسجّل — ولصاحبك :a1 تذكرة ترحيب.'), [':a1' => (string) ($welcomeTickets)]) }}
            </p>

            <ul class="space-y-2">
                @foreach ($deepLinks as $deep)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                        <span class="text-sm min-w-0 truncate">{{ $deep['label'] }}</span>
                        @include('events.components.copy', ['text' => $deep['url'], 'label' => (string) setting('referral.index.include_3', 'نسخ الرابط'), 'tone' => 'ghost'])
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- عدّاد «X أشخاص دعوتهم» + فلاتر القائمة (7.6.2) — فلتران ظاهران لا أكثر (2.15-أ-4) --}}
    <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
        <h2 class="font-bold">
            {{ str_replace(':count', number_format($stats['invited']), (string) setting('referral.list.title', ':count أشخاص دعوتهم')) }}
        </h2>
    </div>

    <form method="get" action="{{ route('referral.index') }}" class="card p-3 mb-4 flex flex-wrap items-end gap-3">
        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('referral.filter.period_label', 'الفترة') }}</span>
            <select name="days" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($periods as $value => $label)
                    <option value="{{ $value }}" @selected((int) $value === (int) $days)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('referral.filter.status_label', 'الحالة') }}</span>
            <select name="status" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($value === $status)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </form>

    @if ($invited->isEmpty())
        <x-empty message="{{ setting('referral.index.message_1', 'ابدأ بدعوة أوّل صديق — الرابط جاهز فوق.') }}" action="{{ setting('referral.index.action_1', 'افتح الفعاليّات') }}" :href="route('events.index')" />
    @else
        {{-- ديسكتوب: جدول بأعمدة محدودة (2.15-أ-5) --}}
        <div class="card overflow-hidden hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken); color: var(--text-muted)">
                        <th class="text-start px-4 py-3 font-medium">{{ setting('referral.index.text_8', 'مَن انضمّ') }}</th>
                        <th class="text-start px-4 py-3 font-medium">{{ setting('referral.index.text_9', 'التاريخ') }}</th>
                        <th class="text-start px-4 py-3 font-medium">{{ setting('referral.index.text_10', 'الحالة') }}</th>
                        <th class="text-start px-4 py-3 font-medium">{{ setting('referral.index.text_11', 'العمولة') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invited as $referral)
                        @php $status = $service->statusOf($referral); @endphp
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3">
                                <span class="flex items-center gap-2">
                                    <x-avatar :user="$referral->referred" size="8" />
                                    <span>{{ $referral->referred?->shortName() ?? (string) setting('referral.index.expr_2', 'حساب غير مكتمل') }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3" style="color: var(--text-muted)" title="{{ $referral->created_at?->format('Y-m-d H:i') }}">
                                {{ $referral->created_at?->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3"><x-state-badge :state="$status['state']" :label="$status['label']" /></td>
                            <td class="px-4 py-3 font-semibold">{{ (float) $referral->commission_earned }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- الموبايل: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="md:hidden space-y-2">
            @foreach ($invited as $referral)
                @php $status = $service->statusOf($referral); @endphp
                <div class="card p-3 flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2 min-w-0">
                        <x-avatar :user="$referral->referred" size="9" />
                        <span class="min-w-0">
                            <span class="block text-sm truncate">{{ $referral->referred?->shortName() ?? (string) setting('referral.index.expr_3', 'حساب غير مكتمل') }}</span>
                            <span class="block text-xs" style="color: var(--text-muted)">{{ $referral->created_at?->diffForHumans() }}</span>
                        </span>
                    </span>
                    <span class="text-end shrink-0">
                        <x-state-badge :state="$status['state']" :label="$status['label']" />
                        <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ (float) $referral->commission_earned }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    @endif
@endsection

@section('mobile_action')
    @include('events.components.copy', ['text' => $link, 'label' => (string) setting('referral.index.include_4', 'نسخ رابط الدعوة')])
@endsection
