@extends('layouts.app')

@section('title', 'تفكيك الهدف')

@php
    /**
     * ⭐ 1.2 التفكيك (23 — 1.2) — مشرف عام المسار.
     *
     * مَعالِم داخل الهدف، وداخل كلّ مَعلَم حزم عمل **مربوطة بالكيان نفسه لا بشخص
     * الدايركتور** — ومنها «الربط الجماعيّ بضغطة واحدة»: حزمة لكلّ كيان من كيانات
     * مساره، وهو حلّ العمل المشترك داخل مَعلَم واحد.
     *
     * وقائمة الكيانات هنا **مسار المستخدم وحده** — وهي تُبنى على الخادم في
     * `EntityScope::linkableEntities`، ويُعاد فحصها عند الحفظ فلا يفيد تزوير الـID.
     */
@endphp

@section('content')
    <x-page-header
        title="تفكيك الهدف"
        :subtitle="$goal->name"
        :breadcrumbs="[['label' => 'رحلة بناء الهدف', 'url' => route('volunteer.goals.build')], ['label' => 'تفكيك الهدف']]">
        @if ($canWrite)
            <x-slot:action>
                <button type="button" data-modal-open="new-milestone"
                        class="btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    <x-icon name="plus" size="16" /> مَعلَم جديد
                </button>
            </x-slot:action>
        @endif
    </x-page-header>

    @if (session('status'))
        <div class="card p-3 mb-4 text-sm"><x-state-badge state="ok" label="تمام" /> {{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="card p-3 mb-4 text-sm" style="border: 1px solid var(--color-state-danger)">
            <x-state-badge state="danger" label="مااتعملش" />
            <ul class="mt-2 space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @unless ($canWrite)
        <div class="card p-3 mb-4 text-sm"><x-state-badge state="warn" label="قراءة فقط" /> {{ $lockMessage }}</div>
    @endunless

    <div class="card p-3 mb-4 text-sm">
        <span style="color: var(--text-muted)">سبب الهدف:</span> {{ $goal->reason ?: '—' }}
        <span class="block text-xs mt-1" style="color: var(--text-muted)">
            معيار التحقّق:
            @if ($goal->verification_type === 'numeric')
                رقم من {{ rtrim(rtrim(number_format((float) $goal->target_from, 2), '0'), '.') }}
                إلى {{ rtrim(rtrim(number_format((float) $goal->target_to, 2), '0'), '.') }}
            @else
                {{ $goal->verification_statement ?: 'حالة تتفحص بنعم/لا' }}
            @endif
        </span>
    </div>

    @forelse ($milestones as $milestone)
        @php $own = $packages->get($milestone->id, collect()); @endphp

        <article class="card p-4 mb-3">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <h2 class="font-semibold">{{ $milestone->name }}</h2>
                    <div class="text-xs mt-1" style="color: var(--text-muted)">
                        {{ $milestone->verification_criteria ?: 'بلا معيار خاصّ — يتبع معيار الهدف' }}
                        @if ($milestone->due_date) · ينتهي {{ $milestone->due_date->format('Y/m/d') }} @endif
                    </div>
                </div>
                <x-state-badge :state="$own->isEmpty() ? 'warn' : 'ok'"
                               :label="$own->count().' حزمة'" />
            </div>

            @if ($own->isNotEmpty())
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($own as $package)
                        <li class="flex items-center gap-2 flex-wrap">
                            <x-icon name="bundle" size="16" />
                            <span>{{ $package->name }}</span>
                            <span class="text-xs" style="color: var(--text-muted)">· {{ $package->entity?->name_ar }}</span>
                            <x-state-badge :state="$package->build_status === 'submitted' ? 'ok' : 'idle'"
                                           :label="$package->build_status === 'submitted' ? 'اترفعت للمراجعة' : 'عند الدايركتور'" />
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($canLinkPackages)
                <div class="mt-3 flex items-center gap-2 flex-wrap">
                    <button type="button" data-modal-open="link-{{ $milestone->id }}"
                            class="btn rounded-xl px-3 py-2 text-xs font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">اربط حزمة بكيان</button>

                    <form method="post" action="{{ route('volunteer.goals.build.packages', $milestone) }}">
                        @csrf
                        <input type="hidden" name="mode" value="bulk">
                        <button type="submit" class="btn rounded-xl px-3 py-2 text-xs motion-standard"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            حزمة لكلّ كيان في مساري ({{ $entities->count() }})
                        </button>
                    </form>
                </div>
            @endif
        </article>
    @empty
        <x-empty message="الهدف لسّه بلا مَعالِم — ابدأ بمَعلَم واحد." />
    @endforelse

    @if ($canOpenFiles)
        {{-- مسودّات الملفّات (23 — 1.2): بابها هنا، وفتحُها ليس هنا --}}
        <section class="card p-4 mt-4">
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <div>
                    <h2 class="font-bold text-sm">مسودّات الملفّات</h2>
                    <p class="text-xs mt-1" style="color: var(--text-muted)">
                        مالقيتش ملفّ شغّال مناسب؟ اعمل مسودّة واربط بيها حزمك — وهتتفعّل بدعواتها
                        لحظة «إرسال للتنفيذ» من مشرف عام التطوّع، مش قبلها.
                    </p>
                </div>

                <button type="button" data-modal-open="file-draft"
                        class="btn rounded-xl px-3 py-2 text-xs font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c; min-height: 44px">
                    مسودّة ملفّ جديدة
                </button>
            </div>

            @if ($fileDrafts->isNotEmpty())
                <ul class="mt-3 space-y-2">
                    @foreach ($fileDrafts as $draft)
                        <li class="flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-sm flex-wrap"
                            style="background: var(--surface-sunken)">
                            <span class="font-semibold">{{ $draft->name_ar }}</span>

                            {{-- الحالة بوسمٍ نصّيّ لا بلونٍ وحده (2.16-ج) --}}
                            <x-state-badge state="warn" label="مسودّة — لسّه ما اتفتحتش" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @push('modals')
        @if ($canWrite)
            <x-modal id="new-milestone" title="مَعلَم جديد">
                <form method="post" action="{{ route('volunteer.goals.build.milestones', $goal) }}" class="space-y-3">
                    @csrf
                    <x-form.input name="name" label="اسم المَعلَم" required />
                    <x-form.input name="verification_criteria" label="معيار تحقّقه (اختياريّ)" />
                    <x-form.input name="due_date" type="date" label="تاريخ الاستحقاق (اختياريّ)" />
                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">أضِف المَعلَم</button>
                </form>
            </x-modal>
        @endif

        @if ($canOpenFiles)
            {{--
             | 23 — 1.2 (سيناريو مشرف عام الملفّات): «إن لم يوجد ملفٌّ مناسب
             | أنشأ أثناء البناء مسودّات ملفّات جديدة وربطها بالحزم».
             | والفورم **يكتب دعوةً ولا يرسلها**: لا إشعار يخرج الآن، لأنّ
             | الملفّ قد لا يُفتَح أصلًا لو لم تضغط القمّة «إرسال للتنفيذ».
             --}}
            <x-modal id="file-draft" title="مسودّة ملفّ جديدة">
                <form method="post" action="{{ route('volunteer.goals.build.file_drafts', $goal) }}" class="space-y-3">
                    @csrf

                    <x-form.input name="name" label="اسم الملفّ" required
                                  hint="الملفّ كيان مؤقّت — بينتهي بقرار القمّة وحدها." />

                    <div class="rounded-xl p-3 text-xs space-y-1"
                         style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text-muted)">
                        <p>الدعوات دي بتتكتب دلوقتي و<strong>ما بتشتغلش</strong> — لا عضويّة ولا إشعار.</p>
                        <p>بتتفعّل كلّها لحظة ضغط «إرسال للتنفيذ» من مشرف عام التطوّع، مش قبلها.</p>
                    </div>

                    @for ($i = 0; $i < (int) setting('goals.build.file_draft.form_rows', 3); $i++)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <label class="block">
                                <span class="block text-xs mb-1" style="color: var(--text-muted)">عضو (اختياريّ)</span>
                                <input type="number" name="invitations[{{ $i }}][user_id]" min="1"
                                       placeholder="كود المستخدم"
                                       class="w-full rounded-xl px-3 py-2 text-sm"
                                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                            </label>
                            <label class="block">
                                <span class="block text-xs mb-1" style="color: var(--text-muted)">البوزشن</span>
                                <select name="invitations[{{ $i }}][position_id]"
                                        class="w-full rounded-xl px-3 py-2 text-sm"
                                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
                                    @foreach ($invitablePositions as $position)
                                        <option value="{{ $position->id }}">{{ $position->name_ar }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    @endfor

                    <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">احفظ المسودّة</button>
                </form>
            </x-modal>
        @endif

        @if ($canLinkPackages)
            @foreach ($milestones as $milestone)
                <x-modal :id="'link-'.$milestone->id" title="اربط حزمة بكيان من مسارك">
                    <form method="post" action="{{ route('volunteer.goals.build.packages', $milestone) }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="mode" value="single">

                        <label class="block">
                            <span class="block text-sm mb-1">الكيان</span>
                            <select name="entity_id" required class="w-full rounded-xl px-3 py-2 text-sm"
                                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                @foreach ($entities as $entity)
                                    <option value="{{ $entity->id }}">{{ $entity->name_ar }} — {{ $entity->track?->name_ar }}</option>
                                @endforeach

                                {{-- مسودّات هذا الهدف: قابلة للربط، **موسومةً بالنصّ** أنّها لم تُفتَح بعد --}}
                                @foreach ($fileDrafts as $draft)
                                    <option value="{{ $draft->id }}">{{ $draft->name_ar }} — {{ $draft->track?->name_ar }} (مسودّة لسّه ما اتفتحتش)</option>
                                @endforeach
                            </select>
                            <span class="block text-xs mt-1" style="color: var(--text-muted)">
                                الربط بالكيان نفسه لا بشخص الدايركتور — فتغيير الشخص لا يكسر الحزمة.
                            </span>
                        </label>

                        <x-form.input name="name" label="اسم الحزمة" :value="$nextPackageName"
                                      hint="سيبه زيّ ما هو لو الاسم الافتراضيّ مناسب." />

                        <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">اربط الحزمة</button>
                    </form>
                </x-modal>
            @endforeach
        @endif
    @endpush
@endsection
