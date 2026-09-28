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
    @php
        // البحث والمسؤول يبقيان مع الرقاقة: الانتقال بين مَعلَمٍ وحزمةٍ لا يُسقِطهما
        $keep = array_filter(['q' => $filters['q'], 'owner' => $filters['owner']]);
        // ونطاق المَعلَم/الحزمة يبقى مع رقاقة المسؤول
        $scope = array_filter(['milestone' => $filters['milestone'], 'package' => $filters['package'], 'q' => $filters['q']]);
    @endphp

    <nav class="goal-filters" aria-label="{{ setting('volunteer.goal_board.filters_aria', 'ترشيح اللوحة') }}">
        <a href="{{ route('volunteer.goals.show', ['goal' => $goal] + $keep) }}"
           @class(['chip', 'chip-on' => ! $filters['milestone'] && ! $filters['package']])>
            {{ setting('volunteer.goal_board.all', 'كلّ الشغل') }}
            <bdi class="chip-count">{{ $taskTotal }}</bdi>
        </a>

        @foreach ($milestones as $milestone)
            @php $own = $packages->where('milestone_id', $milestone->id); @endphp

            <a href="{{ route('volunteer.goals.show', ['goal' => $goal, 'milestone' => $milestone->id] + $keep) }}"
               @class(['chip', 'chip-on' => $filters['milestone'] === $milestone->id && ! $filters['package']])>
                <x-icon :name="$milestone->is_verified ? 'check' : 'task'" size="14" /> {{ $milestone->name }}
            </a>

            @foreach ($own as $package)
                <a href="{{ route('volunteer.goals.show', ['goal' => $goal, 'package' => $package->id] + $keep) }}"
                   @class(['chip', 'chip-sub', 'chip-on' => $filters['package'] === $package->id])>
                    <x-icon name="bundle" size="14" /> {{ $package->name }}
                </a>
            @endforeach
        @endforeach

        {{--
          بحث بالاسم داخل النطاق المرشَّح (2.15-أ-4: فلاتر + بحث). الكونترولر
          كان يقرأ `q` من البداية وما كان له حقلٌ في الشاشة — فلترٌ بلا باب.
          والنطاق الحاليّ يمرّ معه مخفيًّا فلا يقفز البحث إلى الهدف كلّه.
        --}}
        <form method="get" action="{{ route('volunteer.goals.show', $goal) }}" class="goal-search" role="search">
            @if ($filters['milestone'])<input type="hidden" name="milestone" value="{{ $filters['milestone'] }}">@endif
            @if ($filters['package'])<input type="hidden" name="package" value="{{ $filters['package'] }}">@endif
            @if ($filters['owner'])<input type="hidden" name="owner" value="{{ $filters['owner'] }}">@endif
            <x-icon name="search" size="16" />
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   placeholder="{{ setting('volunteer.goal_board.search_placeholder', 'ابحث باسم المهمّة') }}"
                   aria-label="{{ setting('volunteer.goal_board.search_aria', 'بحث في مهامّ الهدف') }}">
            @if ($filters['q'] !== '')
                <a class="goal-search-clear" href="{{ route('volunteer.goals.show', array_filter(['goal' => $goal, 'milestone' => $filters['milestone'], 'package' => $filters['package'], 'owner' => $filters['owner']])) }}"
                   aria-label="{{ setting('volunteer.goal_board.search_clear', 'امسح البحث') }}">✕</a>
            @endif
        </form>
    </nav>

    {{--
      ⭐ «مين شغّال على إيه»: رقاقةٌ لكلّ مسؤول ومعها حِمله المفتوح. تجيب سؤال
      الإدارة الأوّل على البورد (مين متزحّم ومين فاضي) بنظرةٍ، والنقر يرشّح.
    --}}
    @if ($owners->isNotEmpty())
        <nav class="goal-filters goal-owners" aria-label="{{ setting('volunteer.goal_board.owners_aria', 'ترشيح بالمسؤول') }}">
            @foreach ($owners as $row)
                <a href="{{ route('volunteer.goals.show', ['goal' => $goal] + $scope + ($filters['owner'] === $row['user']->id ? [] : ['owner' => $row['user']->id])) }}"
                   @class(['chip', 'chip-owner', 'chip-on' => $filters['owner'] === $row['user']->id])
                   title="{{ $row['user']->shortName() }}">
                    <x-avatar :user="$row['user']" size="6" />
                    <span class="truncate">{{ $row['user']->shortName() }}</span>
                    <bdi class="chip-count">{{ $row['open'] }}</bdi>
                </a>
            @endforeach
        </nav>
    @endif

    {{-- اللوحة: عمودٌ لكلّ حالة مفتوحة بترتيب دورة العمل (23-3.3) --}}
    <div class="goal-board" data-goal-board>
        @foreach ($columns as $column)
            {{--
              العمود `<details>` مفتوحٌ دائمًا على الديسكتوب، وعلى الموبايل — حيث تتراصّ
              الأعمدة عموديًّا — يُطوى فتُرى رؤوس الحالات الخمس دفعةً واحدة وتفتح ما تريد،
              بدل التمرير عبر كلّ كروت «قيد التنفيذ» للوصول إلى «متعثّرة». الطيّ على
              الموبايل يتولّاه السكربت أسفل الصفحة لحظة التحميل.
            --}}
            <details @class(['goal-col', 'goal-col-vacant' => $column['tasks']->isEmpty()]) open
                     data-goal-col aria-label="{{ $column['label'] }}">
                <summary class="goal-col-head">
                    <span class="cluster" style="gap: 6px">
                        <x-state-badge :state="$column['state']" :label="$column['label']" />
                    </span>
                    <bdi class="goal-col-count">{{ $column['tasks']->count() }}</bdi>
                </summary>

                <div class="goal-col-body">
                    @forelse ($column['tasks'] as $task)
                        @php
                            $item = $itemsById->get($task->work_item_id);
                            $package = $item ? $packagesById->get($item->work_package_id) : null;
                            $act = $actions->get($task->id);
                        @endphp

                        {{-- الكارت مقالةٌ لا رابطًا واحدًا: المحتوى رابطٌ للمهمّة، والأفعال أزرارٌ تحته (زرٌّ داخل رابط ماركبٌ باطل ويقفز للصفحة) --}}
                        <article class="task-card motion-standard">
                        <a class="task-card-main" href="{{ route('volunteer.tasks.show', $task) }}">
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
                                {{-- الموعد يقول حالته: متأخّر · قرب · في وقته (2.16-ب: رمزٌ مع اللون) --}}
                                @php $due = $deadlineStates[$task->id] ?? 'idle'; @endphp
                                <span class="task-card-due" data-due="{{ $due }}">
                                    <span aria-hidden="true">{{ state_color($due)['icon'] ?? '' }}</span>
                                    <bdi>{{ $task->deadline_at->translatedFormat('j M · H:i') }}</bdi>
                                </span>
                            @endif
                        </a>

                        @if ($act)
                            {{--
                              ⭐ الفعل من الكارت: نفس نموذج صفحة المهمّة في بوب-أب، بلا مغادرة اللوحة.
                              الزرّ يحمل هدف الفورم وأرقامه الاستشاريّة، والسكربت أسفل الصفحة يصبّها
                              في البوب-أب المشترك لحظة الضغط — فبوب-أبان لكلّ اللوحة لا اثنان لكلّ كارت.
                            --}}
                            <div class="task-card-actions">
                                <button type="button" class="task-act task-act-primary motion-standard"
                                        data-modal-open="board-deliver" data-board-action="deliver"
                                        data-url="{{ $act['deliver'] }}" data-title="{{ $task->title }}"
                                        data-rep="{{ ($act['rep'] >= 0 ? '+' : '').rtrim(rtrim(number_format($act['rep'], 3), '0'), '.') }}">
                                    {{ setting('volunteer.tasks_show.action', 'تسليم') }}
                                </button>
                                <button type="button" class="task-act motion-standard"
                                        data-modal-open="board-block" data-board-action="block"
                                        data-url="{{ $act['block'] }}" data-title="{{ $task->title }}"
                                        data-blocks-left="{{ $act['blocksLeft'] }}" data-halves="{{ $act['halves'] ? '1' : '0' }}">
                                    {{ setting('volunteer.tasks_show.action_2', 'متعثّر') }}
                                </button>
                            </div>
                        @endif
                        </article>
                    @empty
                        {{-- بلا جملةِ فراغ: العدّاد فوقه يقول صفرًا، والسطر يطوّل العمود بلا فائدة --}}
                    @endforelse
                </div>
            </details>
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

    @if ($actions->isNotEmpty())
        @include('volunteer.goals.partials.board-modals')
    @endif
@endsection

@push('scripts')
    <script>
        {{--
         | الموبايل: الأعمدة متراصّة عموديًّا، فتُطوى كلّها إلّا أوّل عمودٍ فيه شغل —
         | رؤوس الحالات كلّها في الشاشة الأولى، وتفتح ما تريد. وعلى الديسكتوب تبقى
         | مفتوحةً كما رُسمت من الخادم (الطيّ هناك بلا معنى: الأعمدة جنبًا إلى جنب).
         --}}
        (function () {
            if (!window.matchMedia('(max-width: 767px)').matches) return;

            var cols = document.querySelectorAll('[data-goal-col]');
            var kept = false;

            cols.forEach(function (col) {
                var hasWork = !!col.querySelector('.task-card');
                if (hasWork && !kept) { kept = true; return; }
                col.open = false;
            });
        })();
    </script>
@endpush

@if ($actions->isNotEmpty())
    @push('scripts')
        <script>
            {{--
             | البوب-أب المشترك يأخذ هويّته من الزرّ الذي فتحه: هدف الفورم، اسم المهمّة،
             | والأرقام الاستشاريّة — ويبدأ بحقولٍ فارغة كلّ مرّة كي لا يُسلَّم مخرجُ
             | مهمّةٍ باسم أخرى. والفتح نفسه يتولّاه `data-modal-open` العامّ.
             --}}
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-board-action]');
                if (!btn) return;

                var modal = document.getElementById(btn.dataset.modalOpen);
                if (!modal) return;

                var form = modal.querySelector('form');
                form.action = btn.dataset.url;
                form.reset();

                var title = modal.querySelector('[data-board-task-title]');
                if (title) title.textContent = btn.dataset.title || '';

                if (btn.dataset.boardAction === 'deliver') {
                    var rep = modal.querySelector('[data-board-rep]');
                    if (rep) rep.textContent = btn.dataset.rep || '';
                } else {
                    var left = modal.querySelector('[data-board-blocks-left]');
                    if (left) left.textContent = btn.dataset.blocksLeft || '0';
                    var halves = modal.querySelector('[data-board-halves]');
                    if (halves) halves.hidden = btn.dataset.halves !== '1';
                }
            });
        </script>
    @endpush
@endif
