@extends('layouts.admin')

@section('title', setting('admin.volunteer.org.alhykl_walbwzshnz_walsaa', 'الهيكل والبوزشنز والسعة'))

@section('content')
    <x-page-header
        :title="setting('admin.volunteer.org.alhykl_walbwzshnz_walsaa', 'الهيكل والبوزشنز والسعة')"
        :subtitle="setting('admin.volunteer.org.alkyanat_althlatha_wbwzshnatha_wmwshrat', 'الكيانات الثلاثة وبوزشناتها ومؤشّرات سعتها — والسعة مؤشّرات لا موانع.')"
        :breadcrumbs="[['label' => setting('admin.volunteer.org.alttwa', 'التطوّع'), 'url' => route('admin.volunteer.index')], ['label' => setting('admin.volunteer.org.alhykl_walsaa', 'الهيكل والسعة')]]">
        <x-slot:action>
            @can('org_chart.edit')
                <button type="button" data-modal-open="entity-modal"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.org.kyan_2', '+ كيان') }}</button>
            @endcan
            <details class="relative">
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm select-none" style="background: var(--surface-raised)" aria-label="{{ setting('admin.volunteer.org.khyarat_akhra', 'خيارات أخرى') }}">⋯</summary>
                <div class="card absolute inset-inline-end-0 mt-2 w-60 p-2 text-sm z-30">
                    @can('capacity.view')<a class="block rounded-lg px-3 py-2 hover:opacity-80" href="{{ route('admin.volunteer.org.capacity') }}">{{ setting('admin.volunteer.org.tqryr_alsaa', 'تقرير السعة') }}</a>@endcan
                    @can('capacity.edit')<button type="button" data-modal-open="override-modal" class="block w-full text-start rounded-lg px-3 py-2 hover:opacity-80">{{ setting('admin.volunteer.org.override_lkyan', 'Override لكيان') }}</button>@endcan
                </div>
            </details>
        </x-slot:action>
    </x-page-header>

    @include('admin.volunteer.partials.tabs', ['current' => 'org'])

    {{-- قفل معلَن: السعة غير مانعة إطلاقًا (13.4-ف) --}}
    <div class="card p-3 mb-4 text-sm flex items-start gap-2" style="border-color: color-mix(in srgb, var(--color-state-warn) 40%, var(--border))">
        <span aria-hidden="true">▲</span>
        <span>{{ setting('admin.volunteer.org.alsaa', 'السعة') }} <strong>{{ setting('admin.volunteer.org.mwshrat_wtnbyhat_fqt', 'مؤشّرات وتنبيهات فقط') }}</strong> {{ setting('admin.volunteer.org.la_twqf_tskyna_wla_trqya_wla_nqla_walmtjawz', '— لا تُوقِف تسكينًا ولا ترقيةً ولا نقلًا. والمتجاوز يظهر في صحّة فريقه بلا تعطيل أحد.') }}</span>
    </div>

    @include('admin.volunteer.partials.promotion-ladder-pending', ['actingApprovals' => $actingApprovals, 'pendingDecisions' => $pendingDecisions])

    {{-- ثلاثة فلاتر ظاهرة + بحث (2.15-أ-4) --}}
    <x-filters :action="route('admin.volunteer.org')">
        <div>
            <label class="block text-xs mb-1" for="f-q" style="color: var(--text-muted)">{{ setting('admin.volunteer.org.bhth_balasm', 'بحث بالاسم') }}</label>
            <input id="f-q" type="search" name="q" value="{{ $filters['q'] }}"
                   class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </div>
        <div>
            <label class="block text-xs mb-1" for="f-track" style="color: var(--text-muted)">{{ setting('admin.volunteer.org.almsar', 'المسار') }}</label>
            <select id="f-track" name="track" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.volunteer.org.alkl', 'الكلّ') }}</option>
                @foreach ($tracks as $track)
                    <option value="{{ $track->id }}" @selected($filters['track'] === $track->id)>{{ $track->name_ar }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--surface-raised)">{{ setting('admin.volunteer.org.fltr', 'فلتر') }}</button>
    </x-filters>

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-kpi :label="setting('admin.volunteer.org.kyanat', 'كيانات')" :value="$entities->count()" icon="entity" />
        <x-kpi :label="setting('admin.volunteer.org.aada_nshtwn', 'أعضاء نشطون')" :value="$members->count()" icon="people" />
        <x-kpi :label="setting('admin.volunteer.org.kyanat_ghyr_shya', 'كيانات غير صحّيّة')" :value="$unhealthy->count()" icon="health" state="warn" />
        <x-kpi :label="setting('admin.volunteer.org.tjawzat', 'تجاوزات')" :value="$overflows->count()" icon="warning" state="danger" />
    </section>

    {{-- شجرة الكيانات: قابلة للطيّ — بديل الكانفاس على الموبايل (2.15-ج) --}}
    <section class="card p-4 md:p-5">
        <h2 class="font-bold mb-3">{{ setting('admin.volunteer.org.shjra_alkyanat', 'شجرة الكيانات') }}</h2>

        @forelse ($entities->whereNull('parent_id') as $root)
            <details class="mb-2" open>
                <summary class="cursor-pointer font-semibold select-none py-1">
                    {{ $root->name_ar }}
                    <span class="text-xs" style="color: var(--text-muted)">({{ $root->track?->name_ar }})</span>
                    @if ($root->status === 'archived')
                        <x-state-badge state="idle" :label="setting('admin.volunteer.org.mwrshf', 'مؤرشف')" />
                    @endif
                    @if (in_array($root->id, $assignableCaseFiles, true))
                        <button type="button" class="text-xs underline ms-2" data-modal-open="case-file-assign-{{ $root->id }}"
                                onclick="event.preventDefault()">{{ setting('admin.volunteer.org.daawt_ado', 'دعوة عضو') }}</button>
                    @endif
                </summary>

                @if (in_array($root->id, $assignableCaseFiles, true))
                    @include('admin.volunteer.partials.case-file-assign', ['entity' => $root])
                @endif

                <div class="ps-4 mt-1 space-y-1">
                    @foreach ($entities->where('parent_id', $root->id) as $child)
                        <div class="flex items-center justify-between gap-2 text-sm py-1">
                            <span>↳ {{ $child->name_ar }}</span>
                            <span class="flex items-center gap-2">
                                @can('org_chart.edit')
                                    <button type="button" class="text-xs underline" data-entity-edit
                                            data-id="{{ $child->id }}" data-track="{{ $child->track_id }}"
                                            data-parent="{{ $child->parent_id }}" data-name="{{ $child->name_ar }}"
                                            data-cap="{{ $child->member_cap }}">{{ setting('admin.volunteer.org.tadyl', 'تعديل') }}</button>
                                    <form method="post" action="{{ route('admin.volunteer.org.entity.archive', $child) }}"
                                          onsubmit="return confirm('{{ setting('admin.volunteer.org.tarshf_alkyan_dh', 'تأرشف الكيان ده؟') }}')">
                                        @csrf
                                        <button type="submit" class="text-xs underline" style="color: var(--text-muted)">{{ setting('admin.volunteer.org.arshfa', 'أرشفة') }}</button>
                                    </form>
                                @endcan
                                @if (in_array($child->id, $assignableCaseFiles, true))
                                    <button type="button" class="text-xs underline" data-modal-open="case-file-assign-{{ $child->id }}">
                                        {{ setting('admin.volunteer.org.daawt_ado', 'دعوة عضو') }}
                                    </button>
                                @endif
                            </span>
                        </div>
                        @if (in_array($child->id, $assignableCaseFiles, true))
                            @include('admin.volunteer.partials.case-file-assign', ['entity' => $child])
                        @endif
                    @endforeach
                </div>
            </details>
        @empty
            {{-- تمييز «مفيش كيانات أصلًا» عن «الفلتر ما طابقش حاجة» (24.2) --}}
            <x-empty :message="setting('admin.volunteer.org.mfysh_kyanat_lsh_abda_bawl_kyan', 'مفيش كيانات لسّه — ابدأ بأوّل كيان.')"
                     :filtered="$filters['q'] !== '' || $filters['track'] !== 0" />
        @endforelse
    </section>

    {{-- البوزشنز الستّة: نطاق الإشراف وسقف الانشغال --}}
    @can('positions.edit')
        <section class="card p-4 md:p-5 mt-4">
            <h2 class="font-bold mb-1">{{ setting('admin.volunteer.org.albwzshnz_ntaq_alishraf_sqf_alanshghal', 'البوزشنز · نطاق الإشراف · سقف الانشغال') }}</h2>
            <p class="text-xs mb-3" style="color: var(--text-muted)">
                {{ setting('admin.volunteer.org.alsaa_2', 'السعة =') }} <strong>{{ setting('admin.volunteer.org.add_ashkhas', 'عدد أشخاص') }}</strong>{{ setting('admin.volunteer.org.wsqf_alanshghal', '، وسقف الانشغال =') }} <strong>{{ setting('admin.volunteer.org.add_mham', 'عدد مهامّ') }}</strong> {{ setting('admin.volunteer.org.mstqlan_tmama', '— مستقلّان تمامًا.') }}
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
                                    <x-state-badge state="honor" :label="setting('admin.volunteer.org.shrfy_bla_slahyat', 'شرفيّ بلا صلاحيّات')" />
                                @endif
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                <label class="text-xs">{{ setting('admin.volunteer.org.adna', 'أدنى') }}
                                    <input type="number" min="0" name="positions[{{ $position->id }}][span_min]" value="{{ $position->span_min }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                                <label class="text-xs">{{ setting('admin.volunteer.org.aftrady', 'افتراضيّ') }}
                                    <input type="number" min="0" name="positions[{{ $position->id }}][span_default]" value="{{ $position->span_default }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                                <label class="text-xs">{{ setting('admin.volunteer.org.aqsa', 'أقصى') }}
                                    <input type="number" min="0" name="positions[{{ $position->id }}][span_max]" value="{{ $position->span_max }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                                <label class="text-xs">{{ setting('admin.volunteer.org.sqf_alanshghal_mham', 'سقف الانشغال (مهامّ)') }}
                                    <input type="number" min="0" name="positions[{{ $position->id }}][task_load_cap]" value="{{ $position->task_load_cap }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                            </div>

                            <input type="hidden" name="positions[{{ $position->id }}][is_active]" value="{{ $position->is_active ? 1 : 0 }}">
                        </div>
                    @endforeach
                </div>

                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.org.ahfz_ntaqat_alishraf', 'احفظ نطاقات الإشراف') }}</button>
            </form>
        </section>
    @endcan

    {{-- مؤشّرات الإشغال: كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">{{ setting('admin.volunteer.org.ishghal_alkyanat', 'إشغال الكيانات') }}</h2>

        @forelse ($capacity as $row)
            <div class="flex items-center justify-between gap-3 py-2 text-sm {{ $loop->last ? '' : 'border-b' }}" style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ $row['entity']->name_ar }}</div>
                    <div class="text-xs" style="color: var(--text-muted)">{{ $row['members'] }} {!! strtr(setting('admin.volunteer.org.mn_v1_shwaghr', 'من :v1 · شواغر'), [':v1' => e($row['cap'])]) !!} {{ $row['vacancies'] }}</div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="h-1.5 w-20 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                        <div class="h-full" style="width: {{ min(100, $row['percent']) }}%; background: var(--color-state-{{ $row['state'] }})"></div>
                    </div>
                    <x-state-badge :state="$row['state']" :label="$row['percent'].'%'" />
                </div>
            </div>
        @empty
            {{-- تمييز «مفيش كيانات أصلًا» عن «فلتر المسار ما طابقش حاجة» (24.2) —
                 هذا القسم لا يتأثّر بفلتر البحث النصّيّ، بالمسار فقط. --}}
            <x-empty :message="setting('admin.volunteer.org.mfysh_kyanat_fy_alntaq_dh', 'مفيش كيانات في النطاق ده.')"
                     :filtered="$filters['track'] !== 0" />
        @endforelse
    </section>

    @can('capacity.edit')
        @include('admin.volunteer.partials.settings-card', [
            'title' => setting('admin.volunteer.org.iadadat_alhykl_walsaa', 'إعدادات الهيكل والسعة'),
            'rows' => $settings,
            'action' => route('admin.volunteer.org.settings.save'),
            'resetAction' => route('admin.volunteer.reset', 'volunteer_org'),
            'lockedKeys' => ['volunteer.org.capacity_is_blocking'],
        ])
    @endcan
@endsection

@push('modals')
    @can('org_chart.edit')
        <x-modal id="entity-modal" :title="setting('admin.volunteer.org.kyan', 'كيان')">
            <form method="post" action="{{ route('admin.volunteer.org.entity.save') }}">
                @csrf
                <input type="hidden" name="id" id="entity-id">

                <label class="block text-sm font-semibold mb-1" for="entity-track">{{ setting('admin.volunteer.org.almsar', 'المسار') }}</label>
                <select name="track_id" id="entity-track" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($tracks as $track)
                        <option value="{{ $track->id }}">{{ $track->name_ar }}@if ($track->is_temporary) {{ setting('admin.volunteer.org.mwqt', '— مؤقّت') }} @endif</option>
                    @endforeach
                </select>

                @unless ($canOpenCaseFile)
                    <p class="text-xs mb-3" style="color: var(--color-state-warn)">
                        {{ setting('admin.volunteer.org.almlf_almwqt_yfthh_wynhyh', '▲ الملفّ المؤقّت يفتحه ويُنهيه') }} <strong>{{ setting('admin.volunteer.org.mshrf_aam_alttwa_whdh', 'مشرف عام التطوّع وحده') }}</strong> {{ setting('admin.volunteer.org.lw_akhtrt_almsar_almwqt_hytrfd_alhfz', '— لو اخترت المسار المؤقّت هيترفض الحفظ.') }}
                    </p>
                @endunless

                <label class="block text-sm font-semibold mb-1" for="entity-parent">{{ setting('admin.volunteer.org.alkyan_alab_akhtyary', 'الكيان الأب (اختياريّ)') }}</label>
                <select name="parent_id" id="entity-parent" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <option value="">{{ setting('admin.volunteer.org.bla_ab_kyan_ryysy', '— بلا أب (كيان رئيسيّ)') }}</option>
                    @foreach ($entities as $entity)
                        <option value="{{ $entity->id }}">{{ $entity->name_ar }}</option>
                    @endforeach
                </select>

                <label class="block text-sm font-semibold mb-1" for="entity-name">{{ setting('admin.volunteer.org.alasm', 'الاسم') }}</label>
                <input type="text" name="name_ar" id="entity-name" required maxlength="120"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="entity-cap">{{ setting('admin.volunteer.org.sqf_alaada_atrkh_fargha_lyhsb_tlqayya', 'سقف الأعضاء (اتركه فارغًا ليُحسَب تلقائيًّا)') }}</label>
                <input type="number" min="1" name="member_cap" id="entity-cap"
                       class="w-full rounded-xl px-3 py-2 text-sm"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.org.ahfz', 'احفظ') }}</button>
            </form>
        </x-modal>
    @endcan

    @can('capacity.edit')
        <x-modal id="override-modal" :title="setting('admin.volunteer.org.override_lkyan', 'Override لكيان')">
            <form method="post" action="{{ route('admin.volunteer.org.override.save') }}">
                @csrf
                <label class="block text-sm font-semibold mb-1" for="ov-entity">{{ setting('admin.volunteer.org.alkyan', 'الكيان') }}</label>
                <select name="entity_id" id="ov-entity" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($entities as $entity)
                        <option value="{{ $entity->id }}">{{ $entity->name_ar }}</option>
                    @endforeach
                </select>

                <label class="block text-sm font-semibold mb-1" for="ov-key">{{ setting('admin.volunteer.org.aliadad', 'الإعداد') }}</label>
                <select name="key" id="ov-key" class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @foreach ($settings as $row)
                        <option value="{{ $row['key'] }}">{{ $row['label'] }}</option>
                    @endforeach
                </select>

                <label class="block text-sm font-semibold mb-1" for="ov-value">{{ setting('admin.volunteer.org.alqyma_aljdyda', 'القيمة الجديدة') }}</label>
                <input type="text" name="value" id="ov-value" required maxlength="255"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <label class="block text-sm font-semibold mb-1" for="ov-reason">{{ setting('admin.volunteer.org.alsbb_ilzamy', 'السبب (إلزاميّ)') }}</label>
                <textarea name="reason" id="ov-reason" rows="2" required minlength="5" maxlength="300"
                          class="w-full rounded-xl px-3 py-2 text-sm"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>

                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.org.ahfz_aloverride', 'احفظ الـOverride') }}</button>
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
