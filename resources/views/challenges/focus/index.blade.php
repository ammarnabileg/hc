@extends('layouts.app')
@section('title', 'حرب التركيز')

@section('content')
    <x-page-header
        title="حرب التركيز"
        subtitle="عمل عميق بلا مقاطعة — والمكافأة دقائق تركيز مش تذاكر."
        :breadcrumbs="[['label' => 'التحديات', 'url' => route('challenges.index')], ['label' => 'حرب التركيز']]">
        <x-slot:action>
            <button type="button" data-modal-open="focus-new"
                    class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">تحدّي جديد</button>
        </x-slot:action>
    </x-page-header>

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-kpi label="دقائق تركيزي" :value="$focusMinutes" icon="🧘" />
        <x-kpi label="تحدّياتي النشطة" :value="$activeOwned.' / '.$maxActive" icon="📌" />
        <x-kpi label="تكلفة الإنشاء" :value="(int) $createCost" icon="🎟️" />
        <x-kpi label="تذاكري" :value="(int) $ticketsBalance" icon="💳" />
    </div>

    {{-- رسالة الأمانة — قلب هذه الحرب (15.3) --}}
    <blockquote class="card p-4 mb-5 text-sm leading-relaxed" style="border-inline-start: 3px solid var(--color-brand-500)">
        {{ $honesty }}
    </blockquote>

    @if ($board->isEmpty())
        <x-empty message="مفيش تحدّيات تركيز نشطة — ابدأ إنت أوّل واحد." />
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
                            @include('challenges.components.war-icon', ['type' => 'focus', 'size' => 36, 'label' => 'حرب تركيز'])
                        </span>
                        <x-state-badge :state="$war->is_group ? 'ok' : 'idle'"
                                       :label="$war->is_group ? 'جماعيّ' : 'فرديّ'" />
                    </div>

                    <div>
                        <h2 class="font-bold">{{ $war->duration_minutes }} دقيقة تركيز</h2>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">
                            {{ $war->intention ?: 'بلا نيّة مكتوبة' }} · صاحبه: {{ $war->owner?->name }}
                        </p>
                    </div>

                    {{-- أكوام الأفاتار: دليل اجتماعيّ يشجّع على الانضمام (15.3) --}}
                    @if ($joiners->isNotEmpty())
                        <div class="flex items-center gap-2">
                            <div class="flex -space-i-3">
                                @foreach ($joiners->take(4) as $member)
                                    <span class="inline-flex" style="margin-inline-start: {{ $loop->first ? 0 : '-0.6rem' }}">
                                        <x-avatar :user="$member->user" size="10" />
                                    </span>
                                @endforeach
                            </div>
                            @if ($extra > 0)
                                <span class="text-xs font-bold" style="color: var(--text-muted)">+{{ $extra }}</span>
                            @endif
                            <span class="text-xs" style="color: var(--text-muted)">منضمّين معاه</span>
                        </div>
                    @endif

                    <div class="mt-auto pt-1 flex flex-wrap gap-2">
                        @if ($row['mine'])
                            <form method="post" action="{{ route('challenges.focus.cancel', $war) }}" class="flex-1">
                                @csrf
                                <button type="submit"
                                        class="w-full rounded-xl px-4 py-2.5 text-sm motion-standard"
                                        style="background: var(--surface-sunken); color: var(--text); border: 1px solid var(--border); min-height: 44px">
                                    ألغِ التحدّي
                                </button>
                            </form>
                        @elseif ($row['joined'])
                            <span class="flex-1 text-center text-xs py-3" style="color: var(--text-muted)">إنت منضمّ — ركّز 🧘</span>
                        @elseif ($war->is_group)
                            <form method="post" action="{{ route('challenges.focus.join', $war) }}" class="flex-1">
                                @csrf
                                <button type="submit"
                                        class="btn w-full rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                                        style="background: var(--color-brand-500); color: #04201c; min-height: 44px">
                                    انضمّ بـ{{ (int) $joinCost }} تذكرة
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @push('modals')
        <x-modal id="focus-new" title="تحدّي تركيز جديد">
            <form method="post" action="{{ route('challenges.focus.store') }}" class="space-y-4 text-sm" id="focus-new-form">
                @csrf

                <fieldset>
                    <legend class="font-bold mb-2">المدّة</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($durations as $minutes)
                            <label class="cursor-pointer">
                                <input type="radio" name="duration_minutes" value="{{ $minutes }}"
                                       class="sr-only peer" @checked($loop->first)>
                                <span class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm motion-standard
                                             peer-checked:font-bold"
                                      style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                    {{ $minutes }} دقيقة
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <label class="block">
                    <span class="block text-sm mb-1">نيّتك (اختياريّ)</span>
                    <input type="text" name="intention" maxlength="240" placeholder="أقرأ كتاب كذا · أخلّص مهمّة كذا"
                           class="w-full rounded-xl px-3 py-3 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_group" value="1" class="w-5 h-5">
                    <span>خلّيه تحدّيًا جماعيًّا — الناس تقدر تنضمّ بتذكرة تروح لك.</span>
                </label>

                <p class="text-xs" style="color: var(--text-muted)">
                    الإنشاء بـ{{ (int) $createCost }} تذاكر وغير قابلة للاسترجاع، وكلّ منضمّ بيدّيك
                    {{ (int) $joinCost }} تذكرة — يعني تحدّي حلو الناس تحبّه = مكسب.
                </p>
            </form>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-xl px-4 py-2.5 text-sm motion-standard"
                            style="background: var(--surface-sunken); color: var(--text); min-height: 44px">مش دلوقتي</button>
                    <button type="submit" form="focus-new-form"
                            class="btn rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c; min-height: 44px">ابدأ التحدّي</button>
                </div>
            </x-slot:footer>
        </x-modal>
    @endpush
@endsection

@section('mobile_action')
    <button type="button" data-modal-open="focus-new"
            class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
            style="background: var(--color-brand-500); color: #04201c">تحدّي تركيز جديد</button>
@endsection
