@extends('layouts.app')
@section('title', setting('challenges.index.title', 'التحديات'))

@section('content')
    <x-page-header
        :title="setting('challenges.index.title', 'التحديات')"
        :subtitle="setting('challenges.index.subtitle', 'حروب بين المحاربين — الرابح ياخد من الخاسر، ومحدّش بيكسب من العدم.')"
        :breadcrumbs="[['label' => setting('challenges.index.breadcrumb_home', 'الرئيسيّة'), 'url' => route('dashboard')], ['label' => setting('challenges.index.title', 'التحديات')]]">
        <x-slot:action>
            <a href="{{ route('challenges.mine') }}"
               class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">{{ setting('challenges.index.mine_action', 'تحدّياتي') }}</a>
        </x-slot:action>
    </x-page-header>

    {{-- أربعة كروت KPI بحدّ أقصى (2.15-أ-3) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-kpi :label="setting('challenges.index.kpi_tickets', 'تذاكري')" :value="(int) $ticketsBalance" icon="ticket" />
        <x-kpi :label="setting('challenges.index.kpi_gate', 'شرط الاستعداد')" :value="$gate" icon="exit" />
        <x-kpi :label="setting('challenges.index.kpi_wins', 'فوزي')" :value="(int) $stat->wins" icon="trophy" />
        <x-kpi :label="setting('challenges.index.kpi_losses', 'خسارتي')" :value="(int) $stat->losses" icon="warning" />
    </div>

    @if ($running)
        <div class="card p-4 mb-4 flex flex-wrap items-center gap-3" style="border-color: var(--color-brand-500)">
            <span aria-hidden="true"><x-icon name="war" size="16" /></span>
            <span class="text-sm font-semibold">{{ setting('challenges.index.running_note', 'عندك مواجهة شغّالة دلوقتي.') }}</span>
            <a href="{{ route('challenges.play', $running) }}"
               class="btn ms-auto rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">{{ setting('challenges.index.resume_match', 'ارجع للمواجهة') }}</a>
        </div>
    @endif

    {{-- ثلاثة فلاتر ظاهرة كحدّ أقصى (2.15-أ-4) --}}
    <x-filters :action="route('challenges.index')">
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('challenges.index.filter_type', 'النوع') }}</span>
            <select name="type" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('challenges.index.filter_type_all', 'كلّ الأنواع') }}</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('challenges.index.filter_state', 'الحالة') }}</span>
            <select name="state" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('challenges.index.filter_state_all', 'الكلّ') }}</option>
                <option value="open" @selected($filters['state'] === 'open')>{{ setting('challenges.index.filter_state_open', 'مفتوحة') }}</option>
                <option value="paused" @selected($filters['state'] === 'paused')>{{ setting('challenges.index.filter_state_paused', 'موقوفة') }}</option>
            </select>
        </label>

        <label class="block flex-1 min-w-40">
            <span class="block text-sm mb-1">{{ setting('challenges.index.filter_search', 'بحث') }}</span>
            <input type="search" name="q" value="{{ $filters['search'] }}" placeholder="{{ setting('challenges.index.filter_search_placeholder', 'اسم الحرب…') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('challenges.index.filter_apply', 'طبّق') }}</button>
    </x-filters>

    @if ($challenges->isEmpty())
        <x-empty :message="setting('challenges.index.empty', 'مفيش ساحات متاحة دلوقتي — تعالى بكرة، الساحة بتتجدّد.')" />
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
                            <x-state-badge state="idle" :label="setting('challenges.index.badge_paused', 'موقوفة مؤقّتًا')" />
                        @elseif ($readiness && (int) $readiness->challenge_id === (int) $challenge->id)
                            <x-state-badge state="ok" :label="setting('challenges.index.badge_ready_here', 'إنت مستعدّ هنا')" />
                        @elseif (! $card['bank_ready'])
                            <x-state-badge state="warn" :label="setting('challenges.index.badge_bank_not_ready', 'البنك مش جاهز')" />
                        @endif
                    </div>

                    <div>
                        <h2 class="font-bold">{{ $challenge->name_ar }}</h2>
                        <p class="text-xs mt-1 line-clamp-2" style="color: var(--text-muted)">{{ $card['tagline'] }}</p>
                    </div>

                    <dl class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)"><x-icon name="trophy" size="16" /> {{ setting('challenges.index.stat_win', 'الفوز') }}</dt>
                            <dd class="font-bold mt-0.5">{{ str_replace(':n', (int) $card['win'], (string) setting('challenges.index.stat_win_value', '+:n تذكرة')) }}</dd>
                        </div>
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)"><x-icon name="warning" size="16" /> {{ setting('challenges.index.stat_loss', 'الخسارة') }}</dt>
                            <dd class="font-bold mt-0.5">{{ str_replace(':n', (int) $card['loss'], (string) setting('challenges.index.stat_loss_value', '−:n تذكرة')) }}</dd>
                        </div>
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">{{ setting('challenges.index.kpi_gate', 'شرط الاستعداد') }}</dt>
                            <dd class="font-bold mt-0.5">{{ str_replace(':n', (int) $card['gate'], (string) setting('challenges.index.stat_gate_value', '≥ :n تذكرة')) }}</dd>
                        </div>
                        <div class="rounded-xl px-3 py-2" style="background: var(--surface-sunken)">
                            <dt style="color: var(--text-muted)">{{ setting('challenges.index.stat_withdraw', 'الانسحاب') }}</dt>
                            <dd class="font-bold mt-0.5">{{ str_replace(':n', (int) $card['withdraw'], (string) setting('challenges.index.stat_withdraw_value', '−:n تذاكر')) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-auto pt-1">
                        @if ($card['type'] === 'focus')
                            <a href="{{ route('challenges.focus.index') }}"
                               class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                               style="background: var(--color-brand-500); color: #04201c">{{ setting('challenges.index.enter_focus', 'ادخل ساحة التركيز') }}</a>
                        @elseif ($challenge->is_active)
                            <a href="{{ route('challenges.arena', $challenge) }}"
                               class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold motion-standard"
                               style="background: var(--color-brand-500); color: #04201c">{{ setting('challenges.index.enter_arena', 'ادخل الساحة') }}</a>
                        @else
                            <p class="text-xs text-center" style="color: var(--text-muted)">{{ setting('challenges.index.reopen_soon', 'هترجع تفتح قريب.') }}</p>
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
       style="background: var(--color-brand-500); color: #04201c">{{ setting('challenges.index.mine_action', 'تحدّياتي') }}</a>
@endsection
