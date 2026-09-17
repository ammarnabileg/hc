@extends('layouts.volunteer')

@section('title', $goal->name)

@section('content')
    <x-page-header :title="$goal->name"
                   :subtitle="setting('volunteer.goal_board.subtitle', 'كلّ مهامّ الهدف في مكان واحد، مرتّبة بحالتها.')"
                   :breadcrumbs="[
                       ['label' => setting('nav.volunteer.item_goals', 'الأهداف والمَعالِم'), 'url' => route('volunteer.goals')],
                       ['label' => $goal->name],
                   ]" />

    {{-- حال الهدف نفسه قبل شغله: النسبة والمَعالِم والنهاية ومعيار التحقّق --}}
    <section class="card p-4 mb-4" aria-label="{{ setting('volunteer.goal_board.summary_aria', 'حالة الهدف') }}">
        <div class="spread" style="gap: 12px; flex-wrap: wrap">
            <span class="small muted">
                {{ setting('volunteer.goals.field_2', 'معيار التحقّق:') }}
                @if ($goal->verification_type === 'numeric')
                    {{ setting('volunteer.goals.field_3', 'رقميّ من') }} <bdi>{{ rtrim(rtrim(number_format((float) $goal->target_from, 2), '0'), '.') }}</bdi>
                    {{ setting('volunteer.common.to', 'إلى') }} <bdi>{{ rtrim(rtrim(number_format((float) $goal->target_to, 2), '0'), '.') }}</bdi>
                @else
                    {{ setting('volunteer.goals.field_4', 'حالة قابلة للفحص بنعم/لا') }}
                @endif
            </span>

            <span class="small muted">
                {{ setting('volunteer.goals.field_5', 'النهاية:') }}
                <bdi>{{ $goal->end_date?->format('Y/m/d') ?? '—' }}</bdi>
            </span>
        </div>

        @include('volunteer.goals.partials.progress', [
            'percent' => (float) $goal->progress_percent,
            'label' => setting('volunteer.goals.label_5', 'الإنجاز بالصعود الآليّ'),
            'closed' => $closedTasks,
        ])

        <div class="mt-2 text-xs" style="color: var(--text-muted)">
            <x-icon name="check" size="16" /> {{ setting('volunteer.goals.field_6', 'مَعالِم مكتملة:') }}
            <span class="font-semibold" style="color: var(--text)">{{ $verified }} {{ setting('volunteer.common.from', 'من') }} {{ $milestoneTotal }}</span>
        </div>
    </section>

    {{--
      ⭐ المرشِّحات مكان التعشيش: المَعلَم ثمّ حزمه. الطبقات الخمس كما هي في
      المنطق، لكنّها هنا صفٌّ من الرقائق لا أربع نقراتٍ متتابعة (2.15-أ-6).
    --}}
    <nav class="goal-filters" aria-label="{{ setting('volunteer.goal_board.filters_aria', 'ترشيح اللوحة') }}">
        <a href="{{ route('volunteer.goals.show', $goal) }}"
           @class(['chip', 'chip-on' => ! $filters['milestone'] && ! $filters['package']])>
            {{ setting('volunteer.goal_board.all', 'كلّ الشغل') }}
            <bdi class="chip-count">{{ $taskTotal }}</bdi>
        </a>

        @foreach ($milestones as $milestone)
            @php $own = $packages->where('milestone_id', $milestone->id); @endphp

            <a href="{{ route('volunteer.goals.show', ['goal' => $goal, 'milestone' => $milestone->id]) }}"
               @class(['chip', 'chip-on' => $filters['milestone'] === $milestone->id && ! $filters['package']])>
                <x-icon :name="$milestone->is_verified ? 'check' : 'task'" size="14" /> {{ $milestone->name }}
            </a>

            @foreach ($own as $package)
                <a href="{{ route('volunteer.goals.show', ['goal' => $goal, 'package' => $package->id]) }}"
                   @class(['chip', 'chip-sub', 'chip-on' => $filters['package'] === $package->id])>
                    <x-icon name="bundle" size="14" /> {{ $package->name }}
                </a>
            @endforeach
        @endforeach
    </nav>

    {{-- اللوحة: عمودٌ لكلّ حالة مفتوحة بترتيب دورة العمل (23-3.3) --}}
    <div class="goal-board" data-goal-board>
        @foreach ($columns as $column)
            <section @class(['goal-col', 'goal-col-vacant' => $column['tasks']->isEmpty()])
                     aria-label="{{ $column['label'] }}">
                <header class="goal-col-head">
                    <span class="cluster" style="gap: 6px">
                        <x-state-badge :state="$column['state']" :label="$column['label']" />
                    </span>
                    <bdi class="goal-col-count">{{ $column['tasks']->count() }}</bdi>
                </header>

                <div class="goal-col-body">
                    @forelse ($column['tasks'] as $task)
                        @php
                            $item = $itemsById->get($task->work_item_id);
                            $package = $item ? $packagesById->get($item->work_package_id) : null;
                        @endphp

                        <a class="task-card motion-standard" href="{{ route('volunteer.tasks.show', $task) }}">
                            <strong class="task-card-title">{{ $task->title }}</strong>

                            @if ($package)
                                <span class="task-card-path">
                                    <x-icon name="bundle" size="12" /> {{ $package->name }}
                                </span>
                            @endif

                            <span class="task-card-foot">
                                @if ($task->owner)
                                    <span class="cluster" style="gap: 5px">
                                        <x-avatar :user="$task->owner" size="6" />
                                        <span class="truncate">{{ $task->owner->shortName() }}</span>
                                    </span>
                                @endif

                                @if ($task->vxp_value)
                                    <bdi class="task-card-vxp">{{ (int) $task->vxp_value }} {{ setting('volunteer.common.vxp', 'VXP') }}</bdi>
                                @endif
                            </span>

                            @if ($task->deadline_at)
                                <span class="task-card-due">
                                    <x-icon name="calendar" size="12" />
                                    <bdi>{{ $task->deadline_at->translatedFormat('j M · H:i') }}</bdi>
                                </span>
                            @endif
                        </a>
                    @empty
                        {{-- بلا جملةِ فراغ: العدّاد فوقه يقول صفرًا، والسطر يطوّل العمود بلا فائدة --}}
                    @endforelse
                </div>
            </section>
        @endforeach

        {{-- المنتهية: عمودٌ واحد مطويّ يحفظ الحالات الثلاث بأسمائها داخله --}}
        <details class="goal-col goal-col-done">
            <summary class="goal-col-head">
                <span>{{ setting('volunteer.goal_board.done', 'منتهية') }}</span>
                <bdi class="goal-col-count">{{ $doneTotal }}</bdi>
            </summary>

            <div class="goal-col-body mt-2">
                @foreach ($doneColumns as $column)
                    @if ($column['tasks']->isNotEmpty())
                        <div class="goal-done-group">
                            <x-state-badge :state="$column['state']" :label="$column['label']" />
                        </div>

                        @foreach ($column['tasks'] as $task)
                            <a class="task-card motion-standard" href="{{ route('volunteer.tasks.show', $task) }}">
                                <strong class="task-card-title">{{ $task->title }}</strong>
                                @if ($task->owner)
                                    <span class="task-card-foot"><span class="truncate">{{ $task->owner->shortName() }}</span></span>
                                @endif
                            </a>
                        @endforeach
                    @endif
                @endforeach

                @if ($doneTotal === 0)
                    <p class="goal-col-empty">{{ setting('volunteer.goal_board.empty_col', 'مفيش حاجة هنا') }}</p>
                @endif
            </div>
        </details>
    </div>
@endsection
