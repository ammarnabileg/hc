@extends('layouts.app')
@section('title', 'التحديات')

@section('content')
    <x-page-header
        title="التحديات"
        subtitle="حروب بين المحاربين — الرابح ياخد من الخاسر، ومحدّش بيكسب من العدم."
        :breadcrumbs="[['label' => 'الرئيسيّة', 'url' => route('dashboard')], ['label' => 'التحديات']]">
        <x-slot:action>
            <a href="{{ route('challenges.mine') }}"
               class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">تحدّياتي</a>
        </x-slot:action>
    </x-page-header>

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-kpi label="تذاكري" :value="(int) $ticketsBalance" icon="🎟️" />
        <x-kpi label="شرط الاستعداد" :value="$gate" icon="🚪" />
        <x-kpi label="فوزي" :value="(int) $stat->wins" icon="🏆" />
        <x-kpi label="خسارتي" :value="(int) $stat->losses" icon="💥" />
    </div>

    @if ($running)
        <div class="card p-4 mb-4 flex flex-wrap items-center gap-3" style="border-color: var(--color-brand-500)">
            <span aria-hidden="true">⚔️</span>
            <span class="text-sm font-semibold">عندك مواجهة شغّالة دلوقتي.</span>
            <a href="{{ route('challenges.play', $running) }}"
               class="btn ms-auto rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">ارجع للمواجهة</a>
        </div>
    @endif

    {{-- ثلاثة فلاتر ظاهرة كحدّ أقصى (2.15-أ-4) --}}
    <x-filters :action="route('challenges.index')">
        <label class="block">
            <span class="block text-sm mb-1">النوع</span>
            <select name="type" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">كلّ الأنواع</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">الحالة</span>
            <select name="state" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="open" @selected($filters['state'] === 'open')>مفتوحة</option>
                <option value="paused" @selected($filters['state'] === 'paused')>موقوفة</option>
            </select>
        </label>

        <label class="block flex-1 min-w-40">
            <span class="block text-sm mb-1">بحث</span>
            <input type="search" name="q" value="{{ $filters['search'] }}" placeholder="اسم الحرب…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">طبّق</button>
    </x-filters>

    @if ($challenges->isEmpty())
        <x-empty message="مفيش ساحات متاحة دلوقتي — تعالى بكرة، الساحة بتتجدّد." />
    @else
        {{-- الكروت شبكة مرنة: عمود واحد على الموبايل بلا تمرير أفقيّ --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($challenges as $challenge)
                @php $card = $arenas[$challenge->id]; @endphp

                <article class="card p-4 flex flex-col gap-3 animate-fadeup" style="animation-delay: {{ $loop->index * 40 }}ms">
                    <div class="flex items-start justify-between gap-3">
                        <span style="color: {{ $challenge->color ?: 'var(--color-brand-400)' }}">
                            @include('challenges.components.war-icon', ['type' => $card['type'], 'size' => 44, 'label' => $challenge->name_ar])
                        </span>

                        @if (! $challenge->is_active)
                            {{-- الحرب الموقوفة تظهر بحالتها ولا تُخفى --}}
                            <x-state-badge state="idle" label="موقوفة مؤقّتًا" />
                        @elseif ($readiness && (int) $readiness->challenge_id === (int) $challenge->id)
                            <x-state-badge state="ok" label="إنت مستعدّ هنا" />
                        @elseif (! $card['bank_ready'])
                            <x-state-badge state="warn" label="البنك مش جاهز" />
                        @endif
                    </div>

                    <div>
                        <h2 class="font-bold">{{ $challenge->name_ar }}</h2>
                        <p class="text-xs mt-1 line-clamp-2" style="color: var(--text-muted)">{{ $card['tagline'] }}</p>
                    </div>

                    <dl class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">🏆 الفوز</dt>
                            <dd class="font-bold mt-0.5">+{{ (int) $card['win'] }} تذكرة</dd>
                        </div>
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">💥 الخسارة</dt>
                            <dd class="font-bold mt-0.5">−{{ (int) $card['loss'] }} تذكرة</dd>
                        </div>
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">شرط الاستعداد</dt>
                            <dd class="font-bold mt-0.5">≥ {{ (int) $card['gate'] }} تذكرة</dd>
                        </div>
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">الانسحاب</dt>
                            <dd class="font-bold mt-0.5">−{{ (int) $card['withdraw'] }} تذاكر</dd>
                        </div>
                    </dl>

                    <div class="mt-auto pt-1">
                        @if ($card['type'] === 'focus')
                            <a href="{{ route('challenges.focus.index') }}"
                               class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                               style="background: var(--color-brand-500); color: #04201c">ادخل ساحة التركيز</a>
                        @elseif ($challenge->is_active)
                            <a href="{{ route('challenges.arena', $challenge) }}"
                               class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                               style="background: var(--color-brand-500); color: #04201c">ادخل الساحة</a>
                        @else
                            <p class="text-xs text-center" style="color: var(--text-muted)">هترجع تفتح قريب.</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('challenges.mine') }}"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">تحدّياتي</a>
@endsection
