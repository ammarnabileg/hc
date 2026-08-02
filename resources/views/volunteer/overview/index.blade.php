@extends('layouts.volunteer')

@section('title', 'رحلتي في التطوّع')

@section('content')
    <x-page-header title="رحلتي في التطوّع"
                   subtitle="كلّ محطّة عدّيتها، بتاريخها وكيانها."
                   :breadcrumbs="[
                       ['label' => 'لوحة التطوّع', 'url' => route('volunteer.overview')],
                       ['label' => 'رحلتي في التطوّع'],
                   ]">
        <x-slot:action>
            <a href="{{ \Illuminate\Support\Facades\Route::has('profile.me') ? route('profile.me') : '#' }}"
               class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">فتح بروفايلي</a>
        </x-slot:action>
    </x-page-header>

    {{-- كارت هويّة مصغّر: الاسم والكود والكيان والبوزشن + شارة Rep (24.4) --}}
    <div class="card p-4 mb-5 flex items-center gap-3 flex-wrap">
        <x-avatar :user="$user" size="12" />
        <div class="min-w-0">
            <div class="font-bold flex items-center gap-2">
                <span>{{ $user->name }}</span>
                @include('volunteer.components.rep-badge', ['user' => $user])
            </div>
            <div class="text-xs" style="color: var(--text-muted)">
                #{{ $user->code }} · {{ $membership?->entity?->name_ar ?? 'بلا كيان' }}
                · {{ $membership?->position?->name_ar ?? '—' }}
            </div>
        </div>
    </div>

    {{-- 4 كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <x-kpi label="مدّة الخدمة" :value="$kpis['service']" icon="hourglass" />
        <x-kpi label="البوزشن الحاليّ" :value="$kpis['position']" icon="badge" />
        <x-kpi label="الشهادات" :value="$kpis['certificates']" icon="certificate" />
        <x-kpi label="إجمالي VXP" :value="$kpis['vxp']" icon="spark" />
    </div>

    @if (empty($stations))
        <x-empty message="رحلتك لسّه في أوّلها — أوّل محطّة اكتملت بالفعل"
                 action="شوف مهامّي" :href="route('volunteer.tasks.index')" />
    @else
        {{-- Roadmap رأسيّ بمحطّات مرقّمة (Stepper) — مرسوم بالـCSS بلا مكتبات --}}
        <ol class="relative space-y-4" style="padding-inline-start: 1.75rem">
            <span class="absolute top-2 bottom-2 w-px" style="inset-inline-start: .55rem; background: var(--border)"></span>

            @foreach ($stations as $index => $station)
                @php
                    $icon = match ($station['type']) {
                        'qualifying' => 'training',
                        'shortlist' => 'note',
                        'interview' => 'announcement',
                        'placement' => 'placement',
                        'certificate' => 'certificate',
                        default => 'badge',
                    };
                @endphp

                <li class="relative card p-4 animate-fadeup" style="animation-delay: {{ min($index * 40, 320) }}ms">
                    <span class="absolute grid place-items-center rounded-full text-[11px] font-bold"
                          style="inset-inline-start: -2.05rem; top: 1rem; width: 1.4rem; height: 1.4rem;
                                 background: var(--color-brand-500); color: #04201c">{{ $index + 1 }}</span>

                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <div class="font-semibold flex items-center gap-2">
                                <span aria-hidden="true">{{ $icon }}</span>
                                <span>{{ $station['title'] }}</span>
                            </div>
                            @if (! empty($station['meta']))
                                <div class="text-xs mt-1" style="color: var(--text-muted)">{{ $station['meta'] }}</div>
                            @endif
                        </div>

                        <div class="text-xs shrink-0 cursor-help"
                             style="color: var(--text-muted)"
                             title="{{ \Illuminate\Support\Carbon::parse($station['at'])->format('Y-m-d') }}">
                            {{ \Illuminate\Support\Carbon::parse($station['at'])->diffForHumans() }}
                        </div>
                    </div>

                    @if (! empty($station['certificate']))
                        <div class="mt-3 flex items-center gap-2">
                            <x-state-badge state="honor" label="شهادة صدرت" />
                            <a class="text-xs underline"
                               href="{{ \Illuminate\Support\Facades\Route::has('verify.certificate')
                                    ? route('verify.certificate', $station['certificate']->code)
                                    : '#' }}">تحميل PDF</a>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>

        {{-- بار «طريقك للبوزشن الجاي» --}}
        <div class="card p-4 mt-6">
            <div class="flex items-center justify-between text-sm">
                <span>طريقك للبوزشن الجاي</span>
                <span style="color: var(--text-muted)">
                    {{ $nextPositionProgress['days'] }} / {{ $nextPositionProgress['target'] }} يوم في البوزشن الحاليّ
                </span>
            </div>
            <div class="mt-2 h-2 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                <div class="h-full motion-standard"
                     style="width: {{ $nextPositionProgress['percent'] }}%; background: var(--color-brand-500)"></div>
            </div>
        </div>
    @endif
@endsection
