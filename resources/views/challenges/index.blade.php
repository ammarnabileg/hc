@extends('layouts.app')
@section('title', 'التحديات')

@section('content')
    @php
        $topupExists = \Illuminate\Support\Facades\Route::has('wallet.topup');
    @endphp

    <x-page-header
        title="التحديات"
        subtitle="اختر حربك وادخلها — والتكلفة والمكافأة قدّامك قبل ما تقرّر."
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
        <x-kpi label="كوينزي" :value="(int) $coinsBalance" icon="💰" />
        <x-kpi label="جارية عليّ" :value="$runningCount" icon="⏳" />
        <x-kpi label="حروب متاحة" :value="$challenges->where('is_active', true)->count()" icon="⚔️" />
    </div>

    {{-- ثلاثة فلاتر ظاهرة + بحث (2.15-أ-4) --}}
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
            <span class="block text-sm mb-1">تكلفة الدخول</span>
            <select name="cost" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">أيّ تكلفة</option>
                <option value="free" @selected($filters['cost'] === 'free')>ببلاش</option>
                <option value="low" @selected($filters['cost'] === 'low')>خفيفة</option>
                <option value="high" @selected($filters['cost'] === 'high')>أعلى</option>
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">المدّة</span>
            <select name="duration" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">أيّ مدّة</option>
                <option value="short" @selected($filters['duration'] === 'short')>سريعة</option>
                <option value="medium" @selected($filters['duration'] === 'medium')>متوسّطة</option>
                <option value="long" @selected($filters['duration'] === 'long')>طويلة</option>
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
        <x-empty message="مفيش تحدّيات متاحة دلوقتي — تعالى بكرة، الساحة بتتجدّد." />
    @else
        {{-- الكروت شبكة مرنة: عمود واحد على الموبايل بلا تمرير أفقيّ --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($challenges as $challenge)
                @php
                    $preview = $previews[$challenge->id];
                    $type = $challenge->limits['type'] ?? 'default';
                    $rewards = $challenge->rewards ?? [];
                    $isRunning = $running->has($challenge->id);
                @endphp

                <article class="card p-4 flex flex-col gap-3 animate-fadeup" style="animation-delay: {{ $loop->index * 40 }}ms">
                    <div class="flex items-start justify-between gap-3">
                        <span style="color: {{ $challenge->color ?: 'var(--color-brand-400)' }}">
                            @include('challenges.components.war-icon', ['type' => $type, 'size' => 44, 'label' => $challenge->name_ar])
                        </span>

                        @if (! $challenge->is_active)
                            {{-- الحرب الموقوفة تظهر بحالتها ولا تُخفى --}}
                            <x-state-badge state="idle" label="موقوفة مؤقّتًا" />
                        @elseif ($isRunning)
                            <x-state-badge state="warn" label="جارية عليك" />
                        @endif
                    </div>

                    <div>
                        <h2 class="font-bold">{{ $challenge->name_ar }}</h2>
                        @if ($challenge->description)
                            <p class="text-xs mt-1 line-clamp-2" style="color: var(--text-muted)">{{ $challenge->description }}</p>
                        @endif
                    </div>

                    <dl class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">تكلفة الدخول</dt>
                            <dd class="font-bold mt-0.5">
                                {{ $preview['cost'] > 0 ? (int) $preview['cost'].' '.$preview['currency_label'] : 'ببلاش' }}
                            </dd>
                        </div>
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">المكافأة</dt>
                            <dd class="font-bold mt-0.5">
                                {{ ($rewards['xp'] ?? 0) > 0 ? $rewards['xp'].' XP' : '' }}
                                {{ ($rewards['tickets'] ?? 0) > 0 ? '+ '.$rewards['tickets'].' 🎟️' : '' }}
                                {{ ($rewards['xp'] ?? 0) + ($rewards['tickets'] ?? 0) === 0 ? 'شرف الفوز' : '' }}
                            </dd>
                        </div>
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">المدّة</dt>
                            <dd class="font-bold mt-0.5">{{ $challenge->duration_minutes ? $challenge->duration_minutes.' دقيقة' : 'بلا وقت' }}</dd>
                        </div>
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">المشاركون</dt>
                            <dd class="font-bold mt-0.5">{{ $participants[$challenge->id] ?? 0 }}</dd>
                        </div>
                    </dl>

                    @if ($challenge->settings_locked)
                        <p class="text-xs" style="color: var(--text-muted)">🔒 إعدادات الحرب مقفولة دلوقتي لأنّها نشطة.</p>
                    @endif

                    <div class="mt-auto pt-1">
                        @if ($isRunning)
                            <a href="{{ route('challenges.play', $running[$challenge->id]) }}"
                               class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                               style="background: var(--color-brand-500); color: #04201c">كمّل تحدّيك</a>
                        @elseif ($challenge->is_active)
                            <button type="button" data-modal-open="enter-{{ $challenge->id }}"
                                    class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c">ادخل التحدّي</button>
                        @else
                            <p class="text-xs text-center" style="color: var(--text-muted)">هترجع تفتح قريب.</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        {{-- بوب-أب الدخول: القواعد · التكلفة · الرصيد قبل/بعد · تأكيد (24.5) --}}
        @push('modals')
            @foreach ($challenges->where('is_active', true) as $challenge)
                @php $preview = $previews[$challenge->id]; @endphp

                <x-modal :id="'enter-'.$challenge->id" :title="'دخول: '.$challenge->name_ar">
                    <div class="space-y-4 text-sm">
                        <section>
                            <h3 class="font-bold mb-1">القواعد باختصار</h3>
                            <ul class="space-y-1 text-xs" style="color: var(--text-muted)">
                                <li>• {{ $challenge->description ?: 'جاوب على المهامّ واحدة واحدة، وتقدّمك بيتحفظ أوّل بأوّل.' }}</li>
                                <li>• لمّا الوقت يخلص، تسليمك بيتم تلقائيًّا وما تخسرش اللي عملته.</li>
                                <li>• لو النت قطع، تقدّمك محفوظ وترجع تكمّل من نفس المكان.</li>
                            </ul>
                        </section>

                        <section class="rounded-xl p-3" style="background: var(--surface-sunken)">
                            <div class="flex items-center justify-between">
                                <span style="color: var(--text-muted)">تكلفة الدخول</span>
                                <span class="font-bold">{{ (int) $preview['cost'] }} {{ $preview['currency_label'] }}</span>
                            </div>
                            <div class="flex items-center justify-between mt-2">
                                <span style="color: var(--text-muted)">رصيدك قبل</span>
                                <span class="font-bold">{{ (int) $preview['before'] }}</span>
                            </div>
                            <div class="flex items-center justify-between mt-2">
                                <span style="color: var(--text-muted)">رصيدك بعد</span>
                                <span class="font-bold" style="color: var(--color-brand-400)">{{ (int) $preview['after'] }}</span>
                            </div>
                        </section>

                        @unless ($preview['affordable'])
                            <p class="text-xs" style="color: var(--color-state-warn)">
                                ▲ رصيدك مش مكفّي — اشحن وارجع كمّل، الحرب مستنّياك.
                            </p>
                        @endunless
                    </div>

                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2">
                            <button type="button" data-modal-close
                                    class="rounded-xl px-4 py-2 text-sm motion-standard"
                                    style="background: var(--surface-sunken); color: var(--text)">مش دلوقتي</button>

                            @if ($preview['affordable'])
                                <form method="post" action="{{ route('challenges.enter', $challenge) }}">
                                    @csrf
                                    <button type="submit"
                                            class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                            style="background: var(--color-brand-500); color: #04201c">أكّد ودخول</button>
                                </form>
                            @elseif ($topupExists)
                                {{-- زرّ الشحن داخل البوب-أب نفسه — بحماية Route::has (المجال يبنيه غيرنا) --}}
                                <a href="{{ route('wallet.topup') }}"
                                   class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                   style="background: var(--color-brand-500); color: #04201c">اشحن</a>
                            @endif
                        </div>
                    </x-slot:footer>
                </x-modal>
            @endforeach
        @endpush
    @endif
@endsection

@section('mobile_action')
    <a href="{{ route('challenges.mine') }}"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">تحدّياتي</a>
@endsection
