@extends('layouts.volunteer')

@section('title', setting('volunteer.goals_build_aggregate.title', 'التجميع والتسعير'))

@php
    /**
     * ⭐ 1.4 الرفع والتجميع والتسعير (23 — 1.4) — مشرف عام المسار.
     *
     *  • **تعديل مباشر على كلّ شيء** بحفظ تلقائيّ فوريّ لكلّ إنبوت — والمحفوظ
     *    يعود إلى الحقل نفسه عند إعادة الفتح (يكتب في الصفّ لا في مسودّة).
     *  • تحت كلّ إنبوت مُعدَّل كلمة **«تمّ التعديل»** ⟵ بوب-أب بكلّ تعديلات هذا
     *    الحقل بعينه: مَن · متى · ماذا كان.
     *  • **التسعير:** مشرف المسار وحده يضيف قيمة VXP لكلّ مهمّة.
     *  • ثمّ **«رفع معاينة للهدف»** ⟵ القفل الطبقيّ: الحيازة للقمّة وهو قارئ فقط —
     *    والرفض بعدها **على الخادم** لا بإخفاء الزرّ.
     */
@endphp

@section('content')
    <x-page-header
        :title="setting('volunteer.goals_build_aggregate.title', 'التجميع والتسعير')"
        :subtitle="$goal->name"
        :breadcrumbs="[['label' => setting('volunteer.goals_build_aggregate.label', 'رحلة بناء الهدف'), 'url' => route('volunteer.goals.build')], ['label' => setting('volunteer.goals_build_aggregate.title', 'التجميع والتسعير')]]">
        @if ($canRaisePreview)
            <x-slot:action>
                <button type="button" data-modal-open="raise-preview"
                        class="btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.goals_build_aggregate.action', 'رفع معاينة للهدف') }}</button>
            </x-slot:action>
        @endif
    </x-page-header>

    @if (session('status'))
        <div class="card p-3 mb-4 text-sm"><x-state-badge state="ok" :label="setting('volunteer.goals_build_aggregate.label_2', 'تمام')" /> {{ session('status') }}</div>
    @endif

    @unless ($canEdit)
        <div class="card p-3 mb-4 text-sm"><x-state-badge state="warn" :label="setting('volunteer.goals_build_aggregate.label_3', 'قراءة فقط')" /> {{ $lockMessage }}</div>
    @endunless

    <div class="card p-4 mb-4 space-y-3">
        @include('volunteer.goals.build.field', [
            'subject' => 'goal', 'id' => $goal->id, 'field' => 'name', 'label' => setting('volunteer.goals_build_aggregate.label_4', 'اسم الهدف'),
            'value' => $goal->name, 'editable' => $canEdit, 'edits' => $edited['goal:'.$goal->id.':name'] ?? 0,
        ])
        @include('volunteer.goals.build.field', [
            'subject' => 'goal', 'id' => $goal->id, 'field' => 'reason', 'label' => setting('volunteer.goals_build_aggregate.label_5', 'سبب الهدف'),
            'value' => $goal->reason, 'editable' => $canEdit, 'type' => 'textarea',
            'edits' => $edited['goal:'.$goal->id.':reason'] ?? 0,
        ])
    </div>

    @foreach ($milestones as $milestone)
        <article class="card p-4 mb-3">
            <div class="flex items-start justify-between gap-3 flex-wrap mb-3">
                <div class="min-w-0 flex-1 space-y-2">
                    @include('volunteer.goals.build.field', [
                        'subject' => 'milestone', 'id' => $milestone->id, 'field' => 'name', 'label' => setting('volunteer.goals_build_aggregate.label_6', 'اسم المَعلَم'),
                        'value' => $milestone->name, 'editable' => $canEdit,
                        'edits' => $edited['milestone:'.$milestone->id.':name'] ?? 0,
                    ])
                    @include('volunteer.goals.build.field', [
                        'subject' => 'milestone', 'id' => $milestone->id, 'field' => 'verification_criteria',
                        'label' => setting('volunteer.goals_build_aggregate.label_7', 'معيار تحقّق المَعلَم'), 'value' => $milestone->verification_criteria,
                        'editable' => $canEdit,
                        'edits' => $edited['milestone:'.$milestone->id.':verification_criteria'] ?? 0,
                    ])
                </div>

                @if ($canDeleteMilestone)
                    <button type="button" data-modal-open="del-m-{{ $milestone->id }}"
                            class="text-xs underline shrink-0" style="color: var(--color-state-danger)">{{ setting('volunteer.goals_build_aggregate.action_2', 'حذف المَعلَم') }}</button>
                @endif
            </div>

            @foreach ($packages->get($milestone->id, collect()) as $package)
                <div class="rounded-xl p-3 mb-2" style="background: var(--surface-raised)">
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                        <div class="min-w-0 flex-1">
                            @include('volunteer.goals.build.field', [
                                'subject' => 'package', 'id' => $package->id, 'field' => 'name', 'label' => setting('volunteer.goals_build_aggregate.label_8', 'اسم الحزمة'),
                                'value' => $package->name, 'editable' => $canEdit,
                                'edits' => $edited['package:'.$package->id.':name'] ?? 0,
                            ])
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="rounded-lg px-2 py-0.5 text-xs font-bold"
                                  style="background: color-mix(in srgb, var(--color-brand-500) 15%, transparent); color: var(--color-brand-500)"
                                  title="{{ $package->entity?->name_ar }}">{{ $icons[$package->id] ?? '—' }}</span>
                            <x-state-badge :state="$package->build_status === 'submitted' ? 'ok' : 'warn'"
                                           :label="$package->build_status === 'submitted' ? setting('volunteer.goals_build_aggregate.label_9', 'اترفعت للمراجعة') : setting('volunteer.goals_build_aggregate.label_10', 'لسّه عند الدايركتور')" />
                            @if ($canDeletePackage)
                                <button type="button" data-modal-open="del-p-{{ $package->id }}"
                                        class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('volunteer.goals_build_aggregate.action_3', 'حذف') }}</button>
                            @endif
                        </div>
                    </div>

                    @foreach ($tasks->get($package->id, collect()) as $task)
                        <div class="mt-3 pt-3 grid grid-cols-1 md:grid-cols-3 gap-2" style="border-top: 1px solid var(--border)">
                            <div class="md:col-span-2">
                                @include('volunteer.goals.build.field', [
                                    'subject' => 'task', 'id' => $task->id, 'field' => 'title', 'label' => setting('volunteer.goals_build_aggregate.label_11', 'المهمّة'),
                                    'value' => $task->title, 'editable' => $canEdit,
                                    'edits' => $edited['task:'.$task->id.':title'] ?? 0,
                                ])
                            </div>

                            <div>
                                @include('volunteer.goals.build.field', [
                                    'subject' => 'task', 'id' => $task->id, 'field' => 'vxp_value',
                                    'label' => setting('volunteer.goals_build_aggregate.label_12', 'قيمة VXP'), 'value' => rtrim(rtrim(number_format((float) $task->vxp_value, 2), '0'), '.'),
                                    'editable' => $canPrice, 'type' => 'number',
                                    'edits' => $edited['task:'.$task->id.':vxp_value'] ?? 0,
                                ])
                            </div>

                            <div class="md:col-span-3">
                                @include('volunteer.goals.build.field', [
                                    'subject' => 'task', 'id' => $task->id, 'field' => 'deliverable_spec',
                                    'label' => setting('volunteer.common.output_format', 'شكل المخرجات'), 'value' => $task->deliverable_spec,
                                    'editable' => $canEdit, 'type' => 'textarea',
                                    'edits' => $edited['task:'.$task->id.':deliverable_spec'] ?? 0,
                                ])
                            </div>

                            @if ($canDeleteTask)
                                <div class="md:col-span-3">
                                    <button type="button" data-modal-open="del-t-{{ $task->id }}"
                                            class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('volunteer.goals_build_aggregate.action_4', 'حذف المهمّة') }}</button>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if ($canEdit)
                        <button type="button" data-modal-open="add-t-{{ $package->id }}"
                                class="mt-3 text-xs underline" style="color: var(--color-brand-500)">
                            + {{ setting('volunteer.goals_build_aggregate.action_5', 'مهمّة جديدة على الحزمة دي') }}
                        </button>
                    @endif
                </div>
            @endforeach
        </article>
    @endforeach

    @if ($milestones->isEmpty())
        <x-empty :message="setting('volunteer.goals_build_aggregate.empty', 'مافيش مَعالِم في الهدف ده بعد.')" />
    @endif

    @push('modals')
        {{-- بوب-أب واحد يُملأ بالـJS: كلّ تعديلات الحقل الذي ضُغِط عليه --}}
        <x-modal id="build-revisions" :title="setting('volunteer.goals_build_aggregate.tooltip', 'تمّ التعديل')">
            <div class="text-sm" data-revisions-body>…</div>
        </x-modal>

        @if ($canEdit)
            @foreach ($packages->flatten() as $package)
                <x-modal :id="'add-t-'.$package->id" :title="setting('volunteer.goals_build_aggregate.tooltip_2', 'مهمّة جديدة على ').$package->name">
                    <form method="post" action="{{ route('volunteer.goals.build.aggregate.task', $goal) }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="package_id" value="{{ $package->id }}">
                        <x-form.input name="title" :label="setting('volunteer.goals_build_aggregate.label_13', 'اسم المهمّة')" required />
                        <label class="block">
                            <span class="block text-sm mb-1">{{ setting('volunteer.common.output_format', 'شكل المخرجات') }}</span>
                            <textarea name="deliverable_spec" rows="2" required class="w-full rounded-xl px-3 py-2 text-sm"
                                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                        </label>
                        <p class="text-xs" style="color: var(--text-muted)">
                            {{ setting('volunteer.goals_build_aggregate.text', 'هتتربط بكيان «') }}{{ $package->entity?->name_ar }}» {{ setting('volunteer.goals_build_aggregate.text_2', 'ومالكها دايركتوره — لا اللي بيكتبها.') }}
                        </p>
                        <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.goals_build_aggregate.action_6', 'أضِف المهمّة') }}</button>
                    </form>
                </x-modal>
            @endforeach
        @endif

        @if ($canRaisePreview)
            <x-modal id="raise-preview" :title="setting('volunteer.goals_build_aggregate.action', 'رفع معاينة للهدف')">
                <form method="post" action="{{ route('volunteer.goals.build.preview', $goal) }}" class="space-y-3 text-sm">
                    @csrf
                    <p>{{ setting('volunteer.goals_build_aggregate.text_3', 'بعد الرفع بيبقى التحرير عند القمّة، وإنت — وقد رفعتها بنفسك — بتبقى') }} <strong>{{ setting('volunteer.goals_build_aggregate.strong', 'قارئ فقط') }}</strong>.</p>
                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('volunteer.goals_build_aggregate.action_7', 'أيوه، ارفع المعاينة') }}</button>
                </form>
            </x-modal>
        @endif

        {{-- تأكيد الحذف: زرّ «لا» أوضح وأكبر من زرّ «نعم» (قاعدة ملزِمة) --}}
        @if ($canDeleteMilestone)
            @foreach ($milestones as $milestone)
                <x-modal :id="'del-m-'.$milestone->id" :title="setting('volunteer.goals_build_aggregate.tooltip_3', 'هل أنت متأكّد من الحذف؟')">
                    <div class="space-y-3 text-sm">
                        <p>{{ setting('volunteer.goals_build_aggregate.text_4', 'هتمسح المَعلَم «') }}{{ $milestone->name }}» {{ setting('volunteer.goals_build_aggregate.text_5', 'بكلّ حزمه ومهامّه.') }}</p>
                        <div class="flex items-center gap-3">
                            <button type="button" data-modal-close
                                    class="btn flex-1 rounded-xl px-5 py-3 text-base font-bold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c; min-height: 48px">{{ setting('volunteer.goals_build_aggregate.action_8', 'لا، رجّعني') }}</button>
                            <form method="post" action="{{ route('volunteer.goals.build.milestones.destroy', $milestone) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('volunteer.goals_build_aggregate.action_9', 'نعم، احذف') }}</button>
                            </form>
                        </div>
                    </div>
                </x-modal>
            @endforeach
        @endif

        @if ($canDeletePackage)
            @foreach ($packages->flatten() as $package)
                <x-modal :id="'del-p-'.$package->id" :title="setting('volunteer.goals_build_aggregate.tooltip_3', 'هل أنت متأكّد من الحذف؟')">
                    <div class="space-y-3 text-sm">
                        <p>{{ setting('volunteer.goals_build_aggregate.text_6', 'هتمسح الحزمة «') }}{{ $package->name }}» {{ setting('volunteer.goals_build_aggregate.text_7', 'بكلّ مهامّها.') }}</p>
                        <div class="flex items-center gap-3">
                            <button type="button" data-modal-close
                                    class="btn flex-1 rounded-xl px-5 py-3 text-base font-bold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c; min-height: 48px">{{ setting('volunteer.goals_build_aggregate.action_8', 'لا، رجّعني') }}</button>
                            <form method="post" action="{{ route('volunteer.goals.build.packages.destroy', $package) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('volunteer.goals_build_aggregate.action_9', 'نعم، احذف') }}</button>
                            </form>
                        </div>
                    </div>
                </x-modal>
            @endforeach
        @endif

        @if ($canDeleteTask)
            @foreach ($tasks->flatten() as $task)
                <x-modal :id="'del-t-'.$task->id" :title="setting('volunteer.goals_build_aggregate.tooltip_3', 'هل أنت متأكّد من الحذف؟')">
                    <div class="space-y-3 text-sm">
                        <p>{{ setting('volunteer.goals_build_aggregate.text_8', 'هتمسح المهمّة «') }}{{ $task->title }}».</p>
                        <div class="flex items-center gap-3">
                            <button type="button" data-modal-close
                                    class="btn flex-1 rounded-xl px-5 py-3 text-base font-bold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c; min-height: 48px">{{ setting('volunteer.goals_build_aggregate.action_8', 'لا، رجّعني') }}</button>
                            <form method="post" action="{{ route('volunteer.goals.build.tasks.destroy', $task) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('volunteer.goals_build_aggregate.action_9', 'نعم، احذف') }}</button>
                            </form>
                        </div>
                    </div>
                </x-modal>
            @endforeach
        @endif
    @endpush
@endsection

@php
    /** نصوص السكربت — تُمرَّر بـ`@json` فلا يبقى حرفٌ عربيّ محروق داخله (2.13-أ) */
    $jsText = [
        'saved' => (string) setting('volunteer.goals_build_aggregate.js_saved', 'اتحفظ ✓'),
        'not_saved' => (string) setting('volunteer.goals_build_aggregate.js_not_saved', 'مااتحفظش'),
        'network_error' => (string) setting('volunteer.goals_build_aggregate.js_network_error', 'الشبكة وقعت — جرّب تاني'),
        'was' => (string) setting('volunteer.goals_build_aggregate.js_was', 'كان:'),
        'empty_value' => (string) setting('volunteer.goals_build_aggregate.js_empty_value', '(فاضي)'),
        'no_edits' => (string) setting('volunteer.goals_build_aggregate.js_no_edits', 'مافيش تعديلات مسجّلة على الحقل ده.'),
    ];
@endphp

@push('scripts')
<script>
const T = @json($jsText);
/*
 | الحفظ التلقائيّ لكلّ إنبوت + بوب-أب «تمّ التعديل» — جافاسكربت خام بلا أيّ
 | مكتبة خارجيّة. وكلّ فعل له ردّ فوريّ بجوار الحقل نفسه (2.17-ب).
 |
 | ولا يعتمد عليه شيءٌ حرِج: الحفظ يقع على الخادم في `saveField`، والقيمة تُقرأ
 | من الصفّ عند إعادة الفتح — فلو تعطّل الجافاسكربت لا يضيع محفوظ.
 */
(function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var CSRF = meta ? meta.getAttribute('content') : '';
    var SAVE = @json(route('volunteer.goals.build.field', $goal));
    var LIST = @json(route('volunteer.goals.build.revisions', $goal));
    var WAIT = @json($debounce);

    document.querySelectorAll('[data-build-field]').forEach(function (box) {
        var input = box.querySelector('[data-build-input]');
        var status = box.querySelector('[data-build-status]');
        var edited = box.querySelector('[data-build-edited]');
        var timer = null;

        if (!input) { return; }

        function save() {
            status.textContent = '…';

            fetch(SAVE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({
                    subject: box.getAttribute('data-subject'),
                    id: box.getAttribute('data-id'),
                    field: box.getAttribute('data-field'),
                    value: input.value,
                }),
            }).then(function (response) {
                return response.json().then(function (data) { return { ok: response.ok, data: data }; });
            }).then(function (result) {
                status.textContent = result.ok ? (result.data.message || T.saved) : (result.data.message || T.not_saved);
                status.style.color = result.ok ? 'var(--color-state-ok)' : 'var(--color-state-danger)';

                if (result.ok && edited && result.data.edits > 0) { edited.classList.remove('hidden'); }
                setTimeout(function () { status.textContent = ''; }, 2500);
            }).catch(function () {
                status.textContent = T.network_error;
                status.style.color = 'var(--color-state-danger)';
            });
        }

        input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(save, WAIT); });
        input.addEventListener('change', function () { clearTimeout(timer); save(); });
    });

    var body = document.querySelector('[data-revisions-body]');

    document.querySelectorAll('[data-build-edited]').forEach(function (link) {
        link.addEventListener('click', function () {
            var parts = link.getAttribute('data-key').split(':');
            var url = LIST + '?subject=' + parts[0] + '&id=' + parts[1] + '&field=' + encodeURIComponent(parts[2]);

            body.textContent = '…';
            document.getElementById('build-revisions').classList.remove('hidden');
            document.getElementById('build-revisions').classList.add('flex');

            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    body.innerHTML = '';
                    var title = document.createElement('div');
                    title.className = 'text-xs mb-2';
                    title.style.color = 'var(--text-muted)';
                    title.textContent = data.label;
                    body.appendChild(title);

                    (data.rows || []).forEach(function (row) {
                        var line = document.createElement('div');
                        line.className = 'rounded-xl p-2 mb-2';
                        line.style.background = 'var(--surface-raised)';
                        line.textContent = row.by + ' · ' + row.at + ' · ' + T.was + ' ' + (row.was || T.empty_value);
                        body.appendChild(line);
                    });

                    if (!data.rows || data.rows.length === 0) {
                        body.appendChild(document.createTextNode(T.no_edits));
                    }
                });
        });
    });
})();
</script>
@endpush
