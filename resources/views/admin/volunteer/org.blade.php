@extends('layouts.app')

@section('title', 'الهيكل والبوزشنز والسعة')

@section('content')
    <x-page-header
        title="الهيكل والبوزشنز والسعة"
        subtitle="الكيانات الثلاثة وبوزشناتها ومؤشّرات سعتها — والسعة مؤشّرات لا موانع."
        :breadcrumbs="[['label' => 'التطوّع', 'url' => route('admin.volunteer.index')], ['label' => 'الهيكل والسعة']]">
        <x-slot:action>
            @can('org_chart.edit')
                <button type="button" data-modal-open="entity-modal"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">+ كيان</button>
            @endcan
            <details class="relative">
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm select-none" style="background: var(--surface-raised)" aria-label="خيارات أخرى">⋯</summary>
                <div class="card absolute inset-inline-end-0 mt-2 w-60 p-2 text-sm z-30">
                    @can('capacity.view')<a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('admin.volunteer.org.capacity') }}">تقرير السعة</a>@endcan
                    @can('capacity.edit')<button type="button" data-modal-open="override-modal" class="block w-full text-start rounded-lg px-3 py-2 hover:opacity-80">Override لكيان</button>@endcan
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    @include('admin.volunteer.partials.tabs', ['current' => 'org'])

    {{-- قفل معلَن: السعة غير مانعة إطلاقًا (13.4-ف) --}}
    <div class="card p-3 mb-4 text-sm flex items-start gap-2" style="border-color: color-mix(in srgb, var(--color-state-warn) 40%, var(--border))">
        <span aria-hidden="true">▲</span>
        <span>السعة <strong>مؤشّرات وتنبيهات فقط</strong> — لا تُوقِف تسكينًا ولا ترقيةً ولا نقلًا. والمتجاوز يظهر في صحّة فريقه بلا تعطيل أحد.</span>
    </div>

    {{-- ثلاثة فلاتر ظاهرة + بحث (2.15-أ-4) --}}
    <x-filters :action="route('admin.volunteer.org')">
        <div>
            <label class="block text-xs mb-1" for="f-q" style="color: var(--text-muted)">بحث بالاسم</label>
            <input id="f-q" type="search" name="q" value="{{ $filters['q'] }}"
                   class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </div>
        <div>
            <label class="block text-xs mb-1" for="f-track" style="color: var(--text-muted)">المسار</label>
            <select id="f-track" name="track" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($tracks as $track)
                    <option value="{{ $track->id }}" @selected($filters['track'] === $track->id)>{{ $track->name_ar }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--surface-raised)">فلتر</button>
    </x-filters>

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-kpi label="كيانات" :value="$entities->count()" icon="🏛️" />
        <x-kpi label="أعضاء نشطون" :value="$members->count()" icon="👥" />
        <x-kpi label="كيانات غير صحّيّة" :value="$unhealthy->count()" icon="🩺" state="warn" />
        <x-kpi label="تجاوزات" :value="$overflows->count()" icon="⚠️" state="danger" />
    </section>

    {{-- شجرة الكيانات: قابلة للطيّ — بديل الكانفاس على الموبايل (2.15-ج) --}}
    <section class="card p-4 md:p-5">
        <h2 class="font-bold mb-3">شجرة الكيانات</h2>

        @forelse ($entities->whereNull('parent_id') as $root)
            <details class="mb-2" open>
                <summary class="cursor-pointer font-semibold select-none py-1">
                    {{ $root->name_ar }}
                    <span class="text-xs" style="color: var(--text-muted)">({{ $root->track?->name_ar }})</span>
                    @if ($root->status === 'archived')
                        <x-state-badge state="idle" label="مؤرشف" />
                    @endif
                </summary>

                <div class="ps-4 mt-1 space-y-1">
                    @foreach ($entities->where('parent_id', $root->id) as $child)
                        <div class="flex items-center justify-between gap-2 text-sm py-1">
                            <span>↳ {{ $child->name_ar }}</span>
                            <span class="flex items-center gap-2">
                                @can('org_chart.edit')
                                    <button type="button" class="text-xs underline" data-entity-edit
                                            data-id="{{ $child->id }}" data-track="{{ $child->track_id }}"
                                            data-parent="{{ $child->parent_id }}" data-name="{{ $child->name_ar }}"
                                            data-cap="{{ $child->member_cap }}">تعديل</button>
                                    <form method="post" action="{{ route('admin.volunteer.org.entity.archive', $child) }}"
                                          onsubmit="return confirm('تأرشف الكيان ده؟')">
                                        @csrf
                                        <button type="submit" class="text-xs underline" style="color: var(--text-muted)">أرشفة</button>
                                    </form>
                                @endcan
                            </span>
                        </div>
                    @endforeach
                </div>
            </details>
        @empty
            <x-empty message="مفيش كيانات لسّه — ابدأ بأوّل كيان." />
        @endforelse
    </section>

    {{-- البوزشنز الستّة: نطاق الإشراف وسقف الانشغال --}}
    @can('positions.edit')
        <section class="card p-4 md:p-5 mt-4">
            <h2 class="font-bold mb-1">البوزشنز · نطاق الإشراف · سقف الانشغال</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted)">
                السعة = <strong>عدد أشخاص</strong>، وسقف الانشغال = <strong>عدد مهامّ</strong> — مستقلّان تمامًا.
            </p>

            <form method="post" action="{{ route('admin.volunteer.org.positions.save') }}">
                @csrf
                <div class="space-y-3">
                    @foreach ($positions as $position)
                        <div class="rounded-xl p-3" style="background: var(--surface-sunken)">
                            <div class="font-semibold text-sm mb-2">
                                {{ $position->name_ar }}
                                <span class="text-xs" style="color: var(--text-muted)">#{{ $position->rank }}</span>
                                @if ($position->is_honorary)
                                    <x-state-badge state="honor" label="شرفيّ بلا صلاحيّات" />
                                @endif
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                <label class="text-xs">أدنى
                                    <input type="number" min="0" name="positions[{{ $position->id }}][span_min]" value="{{ $position->span_min }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                                <label class="text-xs">افتراضيّ
                                    <input type="number" min="0" name="positions[{{ $position->id }}][span_default]" value="{{ $position->span_default }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                                <label class="text-xs">أقصى
                                    <input type="number" min="0" name="positions[{{ $position->id }}][span_max]" value="{{ $position->span_max }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                                <label class="text-xs">سقف الانشغال (مهامّ)
                                    <input type="number" min="0" name="positions[{{ $position->id }}][task_load_cap]" value="{{ $position->task_load_cap }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                            </div>

                            <input type="hidden" name="positions[{{ $position->id }}][is_active]" value="{{ $position->is_active ? 1 : 0 }}">
                        </div>
                    @endforeach
                </div>

                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">احفظ نطاقات الإشراف</button>
            </form>
        </section>
    @endcan

    {{-- مؤشّرات الإشغال: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">إشغال الكيانات</h2>

        @forelse ($capacity as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ $row['entity']->name_ar }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">{{ $row['members'] }} من {{ $row['cap'] }} · شواغر {{ $row['vacancies'] }}</div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="h-1.5 w-20 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                        <div class="h-full" style="width: {{ min(100, $row['percent']) }}%; background: var(--color-state-{{ $row['state'] }})"></div>
                    </div>
                    <x-state-badge :state="$row['state']" :label="$row['percent'].'%'" />
                </div>
            </div>
        @empty
            <x-empty message="مفيش كيانات في النطاق ده." />
        @endforelse
    </section>

    @can('capacity.edit')
        @include('admin.volunteer.partials.settings-card', [
            'title' => 'إعدادات الهيكل والسعة',
            'rows' => $settings,
            'action' => route('admin.volunteer.org.settings.save'),
            'resetAction' => route('admin.volunteer.reset', 'volunteer_org'),
            'lockedKeys' => ['volunteer.org.capacity_is_blocking'],
        ])
    @endcan
@endsection

@push('modals')
    @can('org_chart.edit')
        <x-modal id="entity-modal" title="كيان">
            <form method="post" action="{{ route('admin.volunteer.org.entity.save') }}">
                @csrf
                <input type="hidden" name="id" id="entity-id">

                <label class="block text-sm font-semibold mb-1" for="entity-track">المسار</label>
                <select name="track_id" id="entity-track" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($tracks as $track)
                        <option value="{{ $track->id }}">{{ $track->name_ar }}@if ($track->is_temporary) — مؤقّت @endif</option>
                    @endforeach
                </select>

                @unless ($canOpenCaseFile)
                    <p class="text-xs mb-3" style="color: var(--color-state-warn)">
                        ▲ الملفّ المؤقّت يفتحه ويُنهيه <strong>مشرف عام التطوّع وحده</strong> — لو اخترت المسار المؤقّت هيترفض الحفظ.
                    </p>
                @endunless

                <label class="block text-sm font-semibold mb-1" for="entity-parent">الكيان الأب (اختياريّ)</label>
                <select name="parent_id" id="entity-parent" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">— بلا أب (كيان رئيسيّ)</option>
                    @foreach ($entities as $entity)
                        <option value="{{ $entity->id }}">{{ $entity->name_ar }}</option>
                    @endforeach
                </select>

                <label class="block text-sm font-semibold mb-1" for="entity-name">الاسم</label>
                <input type="text" name="name_ar" id="entity-name" required maxlength="120"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="entity-cap">سقف الأعضاء (اتركه فارغًا ليُحسَب تلقائيًّا)</label>
                <input type="number" min="1" name="member_cap" id="entity-cap"
                       class="w-full rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">احفظ</button>
            </form>
        </x-modal>
    @endcan

    @can('capacity.edit')
        <x-modal id="override-modal" title="Override لكيان">
            <form method="post" action="{{ route('admin.volunteer.org.override.save') }}">
                @csrf
                <label class="block text-sm font-semibold mb-1" for="ov-entity">الكيان</label>
                <select name="entity_id" id="ov-entity" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($entities as $entity)
                        <option value="{{ $entity->id }}">{{ $entity->name_ar }}</option>
                    @endforeach
                </select>

                <label class="block text-sm font-semibold mb-1" for="ov-key">الإعداد</label>
                <select name="key" id="ov-key" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($settings as $row)
                        <option value="{{ $row['key'] }}">{{ $row['label'] }}</option>
                    @endforeach
                </select>

                <label class="block text-sm font-semibold mb-1" for="ov-value">القيمة الجديدة</label>
                <input type="text" name="value" id="ov-value" required maxlength="255"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="ov-reason">السبب (إلزاميّ)</label>
                <textarea name="reason" id="ov-reason" rows="2" required minlength="5" maxlength="300"
                          class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>

                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">احفظ الـOverride</button>
            </form>
        </x-modal>
    @endcan
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('[data-entity-edit]').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById('entity-id').value = btn.dataset.id;
                document.getElementById('entity-track').value = btn.dataset.track;
                document.getElementById('entity-parent').value = btn.dataset.parent || '';
                document.getElementById('entity-name').value = btn.dataset.name;
                document.getElementById('entity-cap').value = btn.dataset.cap || '';
                const modal = document.getElementById('entity-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        });
    </script>
@endpush
