@extends('layouts.volunteer')

@section('title', 'ملء حزم كياني')

@php
    /**
     * ⭐ 1.3 ملء الحزم (23 — 1.3) — دايركتور الكيان، نطاق ENTITY.
     *
     * «يفتح فيرى الهدف والمَعلَم و**حزم العمل الخاصّة به**» — وما وصل الشاشة
     * أصلًا إلّا حزم كياناته: الحصر في `BuildAccess::packagesFor` لا في العرض.
     *
     * وكلّ مهمّة تحمل **أيقونة الكيان**: أوّل حرف من كلّ كلمة في اسم القسم، أو
     * اسم المحافظة لمهام المحافظات — فيبقى منشأ المهمّة معروفًا للأبد.
     */
@endphp

@section('content')
    <x-page-header
        title="ملء حزم كياني"
        :subtitle="$goal->name"
        :breadcrumbs="[['label' => 'رحلة بناء الهدف', 'url' => route('volunteer.goals.build')], ['label' => 'ملء حزم كياني']]" />

    @if (session('status'))
        <div class="card p-3 mb-4 text-sm"><x-state-badge state="ok" label="تمام" /> {{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="card p-3 mb-4 text-sm" style="border: 1px solid var(--color-state-danger)">
            <x-state-badge state="danger" label="مااتحفظش" />
            <ul class="mt-2 space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card p-3 mb-4 text-sm">
        <span style="color: var(--text-muted)">سبب الهدف:</span> {{ $goal->reason ?: '—' }}
        <span class="block text-xs mt-1" style="color: var(--text-muted)">
            ضيف مهامّك «لنفسك» بلا حدّ أقصى — ولمّا تخلص اضغط «رفع للمراجعة» فتروح لمشرف مسارك.
        </span>
    </div>

    @foreach ($packages as $package)
        @php $own = $tasks->get($package->id, collect()); $submitted = $package->build_status === 'submitted'; @endphp

        <article class="card p-4 mb-3">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <h2 class="font-semibold">{{ $package->name }}</h2>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                        المَعلَم: {{ $package->milestone?->name }} · الكيان: {{ $package->entity?->name_ar }}
                    </div>
                </div>
                <x-state-badge :state="$submitted ? 'ok' : 'warn'"
                               :label="$submitted ? 'اترفعت للمراجعة' : $own->count().' مهمّة'" />
            </div>

            @if ($own->isNotEmpty())
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($own as $task)
                        <li class="rounded-xl p-2" style="background: var(--surface-raised)">
                            <div class="flex items-center gap-2 flex-wrap">
                                {{-- أيقونة الكيان: حروف اسم القسم أو اسم المحافظة (23 — 1.3) --}}
                                <span class="rounded-lg px-2 py-0.5 text-xs font-bold"
                                      style="background: color-mix(in srgb, var(--color-brand-500) 15%, transparent); color: var(--color-brand-500)"
                                      title="{{ $package->entity?->name_ar }}">{{ $icons[$package->id] ?? '—' }}</span>
                                <span>{{ $task->title }}</span>
                            </div>
                            @if ($task->deliverable_spec)
                                <div class="text-xs mt-1" style="color: var(--text-muted)">شكل المخرجات: {{ $task->deliverable_spec }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($canWrite && ! $submitted)
                <div class="mt-3 flex items-center gap-2 flex-wrap">
                    <button type="button" data-modal-open="task-{{ $package->id }}"
                            class="btn rounded-xl px-3 py-2 text-xs font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">أضِف مهمّة</button>

                    @if ($own->isNotEmpty())
                        <form method="post" action="{{ route('volunteer.goals.build.submit', $package) }}">
                            @csrf
                            <button type="submit" class="btn rounded-xl px-3 py-2 text-xs motion-standard"
                                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                رفع للمراجعة
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </article>
    @endforeach

    @push('modals')
        @foreach ($packages as $package)
            @continue($package->build_status === 'submitted')
            <x-modal :id="'task-'.$package->id" :title="'مهمّة جديدة — '.$package->name">
                <form method="post" action="{{ route('volunteer.goals.build.tasks', $package) }}" class="space-y-3">
                    @csrf
                    <x-form.input name="title" label="اسم المهمّة" required />
                    <label class="block">
                        <span class="block text-sm mb-1">شكل المخرجات</span>
                        <textarea name="deliverable_spec" rows="2" required class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                        <span class="block text-xs mt-1" style="color: var(--text-muted)">التسليم المتوقَّع حرفيًّا — حقل إلزاميّ في كلّ مهمّة.</span>
                    </label>
                    <label class="block">
                        <span class="block text-sm mb-1">البريف (اختياريّ)</span>
                        <textarea name="brief" rows="2" class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">أضِف المهمّة</button>
                </form>
            </x-modal>
        @endforeach
    @endpush
@endsection
