@extends('layouts.admin')

@section('title', 'الإتاحة والتوقيت')

@section('content')
    @php
        /**
         * إدارة الإتاحة الزمنيّة (الدستور 5 · 12.4).
         * سؤال واحد: متى يُفتَح هذا التدريب ومتى يُقفَل؟
         * عمودان على الديسكتوب، وعلى الموبايل شاشة واحدة: الجدول كروت رأسيّة (2.15-ج).
         */
        $scheduledCount = collect($rows)->reject(fn ($r) => $r['unrestricted'])->count();
        $dailyCount = collect($rows)->filter(fn ($r) => $r['daily'] !== null)->count();
    @endphp

    <x-page-header
        title="الإتاحة والتوقيت"
        subtitle="فترات إتاحة التدريب وأوقات تشغيله اليوميّة — والفتح والغلق يُحسَبان بتوقيت كلّ متدرّب المحلّيّ."
        :breadcrumbs="[
            ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
            ['label' => 'إدارة التدريب', 'url' => route('admin.courses.index')],
            ['label' => 'الإتاحة والتوقيت'],
        ]" />

    {{-- 4 كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-kpi label="تدريبات معروضة" :value="$courses->total()" icon="library" />
        <x-kpi label="لها جدولة زمنيّة" :value="$scheduledCount" icon="calendar" />
        <x-kpi label="لها نافذة يوميّة" :value="$dailyCount" icon="clock" />
        <x-kpi label="توقيتك أنت" :value="$adminTimezone" icon="globe"
               hint="ما تراه هنا مكتوب بتوقيتك — والمتدرّب يراه بتوقيته هو." />
    </div>

    {{-- 3 فلاتر ظاهرة كحدّ أقصى (2.15-أ-4) — وهنا اثنان يكفيان --}}
    <form method="get" action="{{ route('admin.availability.index') }}"
          class="card p-3 mb-4 flex flex-wrap items-end gap-2">
        <label class="flex-1 min-w-48">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">ابحث باسم التدريب</span>
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label>
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الحالة</span>
            <select name="state" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="scheduled" @selected($filters['state'] === 'scheduled')>لها جدولة</option>
                <option value="always" @selected($filters['state'] === 'always')>متاحة دائمًا</option>
            </select>
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">اعرض</button>
    </form>

    <div class="grid gap-4 lg:grid-cols-5">

        {{-- ============================ قائمة التدريبات --}}
        <section class="lg:col-span-2">
            @forelse ($rows as $row)
                @php $isSelected = $selected && $selected->id === $row['course']->id; @endphp

                <a href="{{ route('admin.availability.index', array_merge($filters, ['course' => $row['course']->id])) }}"
                   class="card p-3 mb-2 flex items-start justify-between gap-2 motion-standard hover:opacity-90"
                   @style(['outline: 2px solid var(--color-brand-500)' => $isSelected])>
                    <div class="min-w-0">
                        <div class="font-semibold text-sm truncate">{{ $row['course']->name_ar }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ $row['periods_count'] }} فترة
                            @if ($row['daily'])
                                · يوميًّا {{ $row['daily']['open'] }}–{{ $row['daily']['close'] }}
                            @endif
                        </div>
                    </div>

                    <x-state-badge :state="$row['unrestricted'] ? 'idle' : 'ok'"
                                   :label="$row['unrestricted'] ? 'بلا قيد زمنيّ' : 'مجدول'" />
                </a>
            @empty
                <x-empty message="مفيش تدريبات مطابقة — جرّب بحثًا أوسع." />
            @endforelse

            <div class="mt-3">{{ $courses->links() }}</div>
        </section>

        {{-- ============================ بانل التدريب المختار --}}
        <section class="lg:col-span-3">
            @if (! $selected)
                <x-empty message="اختر تدريبًا من القائمة عشان تضبط إتاحته." />
            @else
                <div class="card p-4 md:p-5">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <h2 class="font-bold">{{ $selected->name_ar }}</h2>
                            <p class="text-xs mt-1" style="color: var(--text-muted)">
                                حالته عندك الآن (بتوقيت {{ $adminTimezone }}):
                            </p>
                        </div>
                        <x-state-badge :state="$state['open'] ? 'ok' : $state['state']"
                                       :label="$state['open'] ? 'مفتوح الآن' : 'مقفول الآن'" />
                    </div>

                    @unless ($state['open'])
                        <p class="text-sm mt-2">{{ $state['reason'] }}</p>
                    @endunless
                </div>

                {{-- ---------------- أوقات التشغيل اليوميّة (5) --}}
                <div class="card p-4 md:p-5 mt-4">
                    <h3 class="font-bold mb-1">أوقات التشغيل اليوميّة</h3>
                    <p class="text-xs mb-3" style="color: var(--text-muted)">
                        خارج الساعات دي التدريب <strong>مقفول</strong> حتى لو فترة الإتاحة سارية.
                        والساعة تُقاس <strong>بتوقيت كلّ متدرّب المحلّيّ</strong> — فـ«5→7 ص» تعني خامسة كلٍّ منهم.
                        سِيب الحقلين فاضيين يبقى مفتوحًا طول اليوم.
                    </p>

                    <form method="post" action="{{ route('admin.availability.daily.save', $selected) }}"
                          class="flex flex-wrap items-end gap-3">
                        @csrf

                        <label>
                            <span class="block text-xs mb-1" style="color: var(--text-muted)">يفتح</span>
                            <input type="time" name="daily_open_at" value="{{ $daily['open'] ?? '' }}"
                                   class="rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </label>

                        <label>
                            <span class="block text-xs mb-1" style="color: var(--text-muted)">يقفل</span>
                            <input type="time" name="daily_close_at" value="{{ $daily['close'] ?? '' }}"
                                   class="rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </label>

                        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">احفظ</button>
                    </form>
                </div>

                {{-- ---------------- فترات الإتاحة المتعدّدة (5) --}}
                <div class="card p-4 md:p-5 mt-4">
                    <h3 class="font-bold mb-1">فترات الإتاحة</h3>
                    <p class="text-xs mb-3" style="color: var(--text-muted)">
                        للتدريب الواحد فترات كثيرة (1→7 يناير، 1→7 مارس…) — والمتدرّب يوصل له أثناء إحداها فقط.
                        وبلا أيّ فترة يبقى التدريب متاحًا في كلّ التواريخ.
                    </p>

                    @forelse ($periods as $period)
                        <div class="flex items-center justify-between gap-2 py-2 {{ $loop->last ? '' : 'border-b' }}"
                             style="border-color: var(--border)">
                            <div class="text-sm">
                                <span>{{ $period->starts_on->translatedFormat('j F Y') }}</span>
                                <span aria-hidden="true" style="color: var(--text-muted)">←</span>
                                <span>{{ $period->ends_on->translatedFormat('j F Y') }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <x-state-badge :state="$period->is_active ? 'ok' : 'idle'"
                                               :label="$period->is_active ? 'فعّالة' : 'موقوفة'" />

                                <form method="post" action="{{ route('admin.availability.periods.toggle', $period) }}">
                                    @csrf
                                    <button class="text-xs underline" style="color: var(--text-muted)">
                                        {{ $period->is_active ? 'أوقفها' : 'فعّلها' }}
                                    </button>
                                </form>

                                <form method="post" action="{{ route('admin.availability.periods.destroy', $period) }}"
                                      onsubmit="return confirm('تحذف الفترة دي؟')">
                                    @csrf @method('delete')
                                    <button class="text-xs underline" style="color: var(--color-state-danger)">احذف</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm py-2" style="color: var(--text-muted)">
                            مفيش فترات — التدريب متاح في كلّ التواريخ حاليًّا.
                        </p>
                    @endforelse

                    @if ($periods->count() < $maxPeriods)
                        <form method="post" action="{{ route('admin.availability.periods.store', $selected) }}"
                              class="flex flex-wrap items-end gap-3 mt-4 pt-4 border-t" style="border-color: var(--border)">
                            @csrf

                            <label>
                                <span class="block text-xs mb-1" style="color: var(--text-muted)">من تاريخ</span>
                                <input type="date" name="starts_on" required
                                       class="rounded-xl px-3 py-2 text-sm"
                                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            </label>

                            <label>
                                <span class="block text-xs mb-1" style="color: var(--text-muted)">إلى تاريخ</span>
                                <input type="date" name="ends_on" required
                                       class="rounded-xl px-3 py-2 text-sm"
                                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            </label>

                            <button class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c">أضِف فترة</button>
                        </form>
                    @endif

                    @error('ends_on')
                        <p class="text-xs mt-2" style="color: var(--color-state-danger)">◉ {{ $message }}</p>
                    @enderror
                </div>
            @endif

            @include('admin.volunteer.partials.settings-card', [
                'title' => 'إعدادات الإتاحة والتوقيت',
                'rows' => $settings,
                'action' => route('admin.availability.settings.save'),
                'resetAction' => route('admin.availability.settings.reset'),
            ])
        </section>
    </div>
@endsection
