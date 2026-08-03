@extends('layouts.app')
@section('title', 'الفعاليّات')
@section('meta_description', 'فعاليّات المنصّة: أونلاين وأوفلاين وهجين — سجّل واحضر واكسب شهادتك.')

@section('content')
    @php
        $modes = \App\Services\Events\EventQuery::MODES;
        $periods = \App\Services\Events\EventQuery::PERIODS;
        $isCalendar = $view === 'calendar';
        $activeAdvanced = (int) (bool) $filters['price'] + (int) (bool) $filters['mine'];
    @endphp

    <x-page-header title="الفعاليّات"
                   subtitle="اختار فعاليّة، سجّل، واحضر — والشهادة والمكافأة بتتفتح بكود الحضور."
                   :breadcrumbs="[['label' => 'الرئيسيّة', 'url' => route('dashboard')], ['label' => 'الفعاليّات']]">
        <x-slot:action>
            {{-- مبدّل العرض: تقويم/كروت (24.5) --}}
            <div class="flex rounded-xl overflow-hidden" style="border: 1px solid var(--border)">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'cards', 'month' => null]) }}"
                   class="px-3 py-2 text-sm motion-standard"
                   style="{{ $isCalendar ? 'background: var(--surface-raised)' : 'background: var(--color-brand-500); color:#04201c; font-weight:700' }}"
                   aria-label="عرض كروت">@include('events.components.icon', ['name' => 'grid']) كروت</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'calendar']) }}"
                   class="px-3 py-2 text-sm motion-standard"
                   style="{{ $isCalendar ? 'background: var(--color-brand-500); color:#04201c; font-weight:700' : 'background: var(--surface-raised)' }}"
                   aria-label="عرض تقويم">@include('events.components.icon', ['name' => 'calendar']) تقويم</a>
            </div>

            {{-- الفعل الرئيسيّ الواحد (2.15-أ-2) --}}
            <a href="{{ request()->fullUrlWithQuery(['mine' => 1, 'period' => 'all']) }}"
               class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">
                @include('events.components.icon', ['name' => 'ticket']) تذاكري ({{ $myTicketsCount }})
            </a>
        </x-slot:action>
    </x-page-header>

    @if (session('error'))
        <x-toast :message="session('error')" state="danger" />
    @endif

    {{-- ثلاثة فلاتر ظاهرة + بحث، والباقي مطويّ (2.15-أ-4) --}}
    <x-filters :action="route('events.index')">
        <input type="hidden" name="view" value="{{ $view }}">
        @if ($month)<input type="hidden" name="month" value="{{ $month }}">@endif

        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">النوع</span>
            <select name="mode" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">كلّ الأنواع</option>
                @foreach ($modes as $key => $label)
                    <option value="{{ $key }}" @selected($filters['mode'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الفترة</span>
            <select name="period" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($periods as $key => $label)
                    <option value="{{ $key }}" @selected($filters['period'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">التصنيف</span>
            <select name="category" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">كلّ التصنيفات</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </label>

        <label class="block flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="ابحث باسم الفعاليّة أو المكان"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">تصفية</button>

        <x-slot:advanced>
            <label class="block">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">السعر</span>
                <select name="price" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">الكلّ</option>
                    <option value="free" @selected($filters['price'] === 'free')>مجّانيّ</option>
                    <option value="paid" @selected($filters['price'] === 'paid')>مدفوع</option>
                </select>
            </label>

            <label class="flex items-center gap-2 text-sm pb-2">
                <input type="checkbox" name="mine" value="1" @checked($filters['mine'])>
                <span>تسجيلاتي فقط @if ($activeAdvanced)<span style="color: var(--color-brand-500)">({{ $activeAdvanced }})</span>@endif</span>
            </label>
        </x-slot:advanced>
    </x-filters>

    @if ($isCalendar)
        @include('events.components.calendar', ['calendar' => $calendar, 'weekdays' => $weekdays, 'presenter' => $presenter])
    @elseif ($events->isEmpty())
        {{-- الحالة الفارغة: سطر واحد + زرّ واحد (2.15-د) --}}
        <x-empty message="مفيش فعاليّات في المدى ده — جرّب توسّع الفترة."
                 action="وسّع المدى"
                 :href="request()->fullUrlWithQuery(['period' => 'all', 'mine' => null])" />
    @else
        {{-- `min-w-0` على أعمدة الشبكة: عنوان الكارت `truncate` — وهو `nowrap` —
             يساهم بمقاس محتواه في تحديد عرض العمود، فيمدّه فوق الشاشة (2.15-ج) --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 [&>*]:min-w-0">
            @foreach ($events as $event)
                @include('events.components.card', [
                    'event' => $event,
                    'presenter' => $presenter,
                    'myRegistrations' => $myRegistrations,
                ])
            @endforeach
        </div>

        <div class="mt-5">{{ $events->links() }}</div>
    @endif
@endsection

@section('mobile_action')
    <a href="{{ request()->fullUrlWithQuery(['mine' => 1, 'period' => 'all']) }}"
       class="btn w-full flex items-center justify-center rounded-xl px-4 py-3 text-sm font-bold motion-standard"
       style="background: var(--color-brand-500); color: #04201c">تذاكري ({{ $myTicketsCount }})</a>
@endsection
