@extends('layouts.app')
@section('title', 'ادعُ أصدقاءك')
@section('meta_description', 'ادعُ أصدقاءك واكسب عمولة على شحناتهم — ولصاحبك تذكرة ترحيب.')

@section('content')
    @php
        $shareText = trim((string) setting('referral.share.text', 'انضمّ معايا على المنصّة — هتلاقي تدريبات وشهادات حقيقيّة:')).' '.$link;
        $welcomeTickets = (int) setting('referral.welcome_tickets', 1);
    @endphp

    <x-page-header title="ادعُ أصدقاءك"
                   subtitle="كلّ صاحب تجيبه ليه تذكرة ترحيب، وليك عمولة على شحناته."
                   :breadcrumbs="[['label' => 'الرئيسيّة', 'url' => route('dashboard')], ['label' => 'ادعُ أصدقاءك']]">
        <x-slot:action>
            @include('events.components.copy', ['text' => $link, 'label' => 'نسخ رابط الدعوة'])
        </x-slot:action>
    </x-page-header>

    {{-- الصفحة اللي اتدعيت ليها تُفتَح لك بعد التسجيل (21.1-ج) --}}
    @if ($landingUrl)
        <div class="card p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
            <span class="text-sm">اتدعيت لـ«{{ $landingLabel ?? 'صفحة معيّنة' }}» — نكمّل من هناك؟</span>
            <a href="{{ $landingUrl }}" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">افتح الصفحة</a>
        </div>
    @endif

    {{-- ⭐ الكارت الواحد البارز: الرابط + العدّاد + العمولة + المشاركة (24.5) --}}
    <div class="card p-5 mb-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="text-xs mb-1" style="color: var(--text-muted)">رابط دعوتك الخاصّ</div>
                <div class="rounded-xl px-3 py-2 text-sm break-all" style="background: var(--surface-sunken)">{{ $link }}</div>

                <div class="flex flex-wrap gap-2 mt-3">
                    @include('events.components.copy', ['text' => $link, 'label' => 'نسخ'])

                    <a href="https://wa.me/?text={{ urlencode($shareText) }}" target="_blank" rel="noopener"
                       class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @include('events.components.icon', ['name' => 'share']) واتساب
                    </a>

                    <a href="https://t.me/share/url?url={{ urlencode($link) }}&text={{ urlencode((string) setting('referral.share.text', 'انضمّ معايا على المنصّة')) }}"
                       target="_blank" rel="noopener"
                       class="btn inline-flex items-center gap-1 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @include('events.components.icon', ['name' => 'share']) تيليجرام
                    </a>
                </div>
            </div>

            <div class="text-center rounded-2xl px-5 py-4"
                 style="background: color-mix(in srgb, var(--color-brand-500) 12%, transparent)">
                <div class="text-3xl font-extrabold" style="color: var(--color-brand-500)"
                     data-count-to="{{ $stats['percent'] }}">{{ $stats['percent'] }}</div>
                <div class="text-xs mt-1" style="color: var(--text-muted)">% عمولة على شحنات مَن تدعوهم</div>
            </div>
        </div>
    </div>

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid gap-3 grid-cols-2 lg:grid-cols-4 mb-4">
        <x-kpi label="المدعوّون" :value="$stats['invited']" icon="👥" />
        <x-kpi label="أتمّوا التفعيل" :value="$stats['completed']" icon="✅" />
        <x-kpi label="في الانتظار" :value="$stats['pending']" icon="⏳" />
        <x-kpi label="العمولة المكتسبة" :value="$stats['commission']" icon="💰"
               :hint="'نسبتك '.$stats['percent'].'% مدى الحياة'" />
    </div>

    {{-- ⭐ رابط دعوة لكلّ محتوى (Deep link) — 21.1-ج --}}
    @if ($deepLinks)
        <div class="card p-5 mb-4">
            <h2 class="font-bold mb-1">ادعُ صديقك لمحتوى بعينه</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted)">
                الرابط ده بيفتح الصفحة نفسها لصاحبك بعد ما يسجّل — ولصاحبك {{ $welcomeTickets }} تذكرة ترحيب.
            </p>

            <ul class="space-y-2">
                @foreach ($deepLinks as $deep)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                        <span class="text-sm min-w-0 truncate">{{ $deep['label'] }}</span>
                        @include('events.components.copy', ['text' => $deep['url'], 'label' => 'نسخ الرابط', 'tone' => 'ghost'])
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- الفلتر الوحيد هنا: الفترة (24.5) --}}
    <form method="get" action="{{ route('referral.index') }}" class="card p-3 mb-4 flex flex-wrap items-end gap-3">
        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الفترة</span>
            <select name="days" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($periods as $value => $label)
                    <option value="{{ $value }}" @selected((int) $value === (int) $days)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </form>

    @if ($invited->isEmpty())
        <x-empty message="ابدأ بدعوة أوّل صديق — الرابط جاهز فوق." action="افتح الفعاليّات" :href="route('events.index')" />
    @else
        {{-- ديسكتوب: جدول بأعمدة محدودة (2.15-أ-5) --}}
        <div class="card overflow-hidden hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--surface-sunken); color: var(--text-muted)">
                        <th class="text-start px-4 py-3 font-medium">مَن انضمّ</th>
                        <th class="text-start px-4 py-3 font-medium">التاريخ</th>
                        <th class="text-start px-4 py-3 font-medium">الحالة</th>
                        <th class="text-start px-4 py-3 font-medium">العمولة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invited as $referral)
                        @php $status = $service->statusOf($referral); @endphp
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="px-4 py-3">
                                <span class="flex items-center gap-2">
                                    <x-avatar :user="$referral->referred" size="8" />
                                    <span>{{ $referral->referred?->shortName() ?? 'حساب غير مكتمل' }}</span>
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
                            <span class="block text-sm truncate">{{ $referral->referred?->shortName() ?? 'حساب غير مكتمل' }}</span>
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
    @include('events.components.copy', ['text' => $link, 'label' => 'نسخ رابط الدعوة'])
@endsection
