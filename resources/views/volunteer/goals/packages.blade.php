@extends('layouts.volunteer')

@section('title', 'حزم العمل')

@section('content')
    <x-page-header
        title="حزم العمل"
        subtitle="الحزم المربوطة بكيانك — وكلّ حزمة بنودها التي تُربَط بها المهامّ."
        :breadcrumbs="[['label' => 'الأهداف والمَعالِم', 'url' => route('volunteer.goals')], ['label' => 'حزم العمل']]" />

    <x-filters :action="route('volunteer.packages')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الكيان</span>
            <select name="entity" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">كلّ كياناتي</option>
                @foreach ($memberships as $membership)
                    <option value="{{ $membership->entity_id }}" @selected($filters['entity'] === (int) $membership->entity_id)>
                        {{ $membership->entity?->name_ar }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="ابحث باسم الحزمة…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>
    </x-filters>

    @if ($packages->isEmpty())
        <x-empty message="مفيش حزم مربوطة بكيانك حاليًّا" action="ارجع للأهداف" :href="route('volunteer.goals')" />
    @else
        <div class="space-y-3">
            @foreach ($packages as $package)
                @php $c = $counts[$package->id]; @endphp
                <a href="{{ route('volunteer.packages.show', $package) }}"
                   class="card p-4 block motion-standard hover:opacity-90 animate-fadeup">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <div class="font-bold"><x-icon name="bundle" size="16" /> {{ $package->name }}</div>
                            <div class="text-xs mt-1" style="color: var(--text-muted)">
                                المَعلَم الأمّ: {{ $package->milestone?->name ?? '—' }} · الكيان: {{ $package->entity?->name_ar ?? '—' }}
                            </div>
                        </div>
                        <div class="text-xs shrink-0" style="color: var(--text-muted)">
                            معتمدة {{ $c['done'] }} / {{ $c['denominator'] }}
                            @if ($c['closed'] > 0)
                                · <span style="color: var(--color-state-idle)">○ {{ $c['closed'] }} مُغلَقة</span>
                            @endif
                        </div>
                    </div>

                    @include('volunteer.goals.partials.progress', [
                        'percent' => (float) $package->progress_percent,
                        'label' => 'نسبة الحزمة',
                        'closed' => $c['closed'],
                    ])
                </a>
            @endforeach
        </div>
    @endif
@endsection
