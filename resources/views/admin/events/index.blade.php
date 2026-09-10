@extends('layouts.admin')

@section('title', setting('admin.events.index.alfaalyat', 'الفعاليّات'))

@section('content')
    <x-page-header
        :title="setting('admin.events.index.alfaalyat', 'الفعاليّات')"
        :subtitle="setting('admin.events.index.allqaat_almbashra_awflayn_wawnlayn_whjyn', 'اللقاءات المباشرة أوفلاين وأونلاين وهجين — بسعتها وكود حضورها ومكافأتها المتدرّجة.')"
        :breadcrumbs="[['label' => setting('admin.events.index.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')], ['label' => setting('admin.events.index.alfaalyat', 'الفعاليّات')]]">
        <x-slot:action>
            @can('events.create')
                <button type="button" data-modal-open="event-modal"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.events.index.faalya_2', '+ فعاليّة') }}</button>
            @endcan
        </x-slot:action>
    </x-page-header>

    <div class="card p-3 mb-4 text-sm">
        <x-icon name="lock" size="16" /> <strong>{{ setting('admin.events.index.kwd_alhdwr_mstmr_la_yqfl', 'كود الحضور مستمرّ لا يقفل') }}</strong> {{ setting('admin.events.index.walmkafaa_whdha_ttnaqs_ala_drjat_zmnya', '— والمكافأة وحدها تتناقص على درجات زمنيّة. وشهادة الحضور تُضبَط في إدارة الشهادات لا هنا.') }}
    </div>

    <x-filters :action="route('admin.events.index')">
        <div>
            <label class="block text-xs mb-1" for="f-q" style="color: var(--text-muted)">{{ setting('admin.events.index.bhth_balasm', 'بحث بالاسم') }}</label>
            <input id="f-q" type="search" name="q" value="{{ $filters['q'] }}"
                   class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </div>
        <div>
            <label class="block text-xs mb-1" for="f-mode" style="color: var(--text-muted)">{{ setting('admin.events.index.alnwa', 'النوع') }}</label>
            <select id="f-mode" name="mode" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">{{ setting('admin.events.index.alkl', 'الكلّ') }}</option>
                @foreach ($modes as $key => $label)
                    <option value="{{ $key }}" @selected($filters['mode'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs mb-1" for="f-status" style="color: var(--text-muted)">{{ setting('admin.events.index.alhala', 'الحالة') }}</label>
            <select id="f-status" name="status" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach (['upcoming' => setting('admin.events.index.qadma', 'قادمة'), 'past' => setting('admin.events.index.mnthya', 'منتهية'), 'draft' => setting('admin.events.index.mswda', 'مسودّة')] as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--surface-raised)">{{ setting('admin.events.index.fltr', 'فلتر') }}</button>
    </x-filters>

    {{--
        ⭐ [2026-09-10] «عرض تقويم + جدول (تبديل)» (12.11) — كان `view=` يُقرَأ
        في المتحكّم بلا زرّ تبديلٍ ولا فرعٍ يستهلكه. تقويم الشهر مبنيٌّ بأيدينا
        بلا أيّ مكتبة خارجيّة (2.16-ج).
    --}}
    <div class="flex items-center gap-2 mb-4">
        <a href="{{ route('admin.events.index', array_filter(['q' => $filters['q'], 'mode' => $filters['mode'], 'status' => $filters['status'], 'view' => 'table'])) }}"
           class="rounded-xl px-4 py-2 text-sm font-semibold"
           style="background: {{ $view === 'table' ? 'var(--color-brand-500)' : 'var(--surface-raised)' }}; color: {{ $view === 'table' ? '#04201c' : 'var(--text)' }}">{{ setting('admin.events.index.jdwl', 'جدول') }}</a>
        <a href="{{ route('admin.events.index', array_filter(['q' => $filters['q'], 'mode' => $filters['mode'], 'status' => $filters['status'], 'view' => 'calendar'])) }}"
           class="rounded-xl px-4 py-2 text-sm font-semibold"
           style="background: {{ $view === 'calendar' ? 'var(--color-brand-500)' : 'var(--surface-raised)' }}; color: {{ $view === 'calendar' ? '#04201c' : 'var(--text)' }}">{{ setting('admin.events.index.tqwym', 'تقويم') }}</a>
    </div>

    @if ($view === 'calendar')
        <div class="card p-3 md:p-4 mb-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <a href="{{ route('admin.events.index', array_filter(['q' => $filters['q'], 'mode' => $filters['mode'], 'status' => $filters['status'], 'view' => 'calendar', 'month' => $month->copy()->subMonthNoOverflow()->format('Y-m')])) }}"
                   class="rounded-lg px-3 py-1.5 text-sm" style="background: var(--surface-sunken)">‹ {{ setting('admin.events.index.alshhr_alsabq', 'الشهر السابق') }}</a>
                <span class="font-semibold text-sm">{{ $month->translatedFormat('F Y') }}</span>
                <a href="{{ route('admin.events.index', array_filter(['q' => $filters['q'], 'mode' => $filters['mode'], 'status' => $filters['status'], 'view' => 'calendar', 'month' => $month->copy()->addMonthNoOverflow()->format('Y-m')])) }}"
                   class="rounded-lg px-3 py-1.5 text-sm" style="background: var(--surface-sunken)">{{ setting('admin.events.index.alshhr_altaly', 'الشهر التالي') }} ›</a>
            </div>

            {{-- شبكة الشهر تمرّر أفقيًّا داخل حاويتها وحدها لا الصفحة (2.15-ج) --}}
            <div class="min-w-0 overflow-x-auto">
                <div class="grid grid-cols-7 gap-1" style="min-width: 560px">
                    @foreach ([
                        setting('admin.events.index.ahd', 'أحد'), setting('admin.events.index.athnyn', 'إثنين'), setting('admin.events.index.thlatha', 'ثلاثاء'),
                        setting('admin.events.index.arbaa', 'أربعاء'), setting('admin.events.index.khmys', 'خميس'), setting('admin.events.index.jmaa', 'جمعة'), setting('admin.events.index.sbt', 'سبت'),
                    ] as $dayName)
                        <div class="text-xs font-semibold text-center py-1" style="color: var(--text-muted)">{{ $dayName }}</div>
                    @endforeach

                    @php $leading = $month->copy()->startOfMonth()->dayOfWeek; @endphp
                    @for ($i = 0; $i < $leading; $i++)
                        <div></div>
                    @endfor

                    @for ($day = 1; $day <= $month->daysInMonth; $day++)
                        <div class="rounded-lg p-1.5" style="background: var(--surface-sunken); min-height: 5.5rem">
                            <div class="text-xs tabular-nums" style="color: var(--text-muted)">{{ $day }}</div>
                            <div class="space-y-1 mt-1">
                                @foreach ($calendarEvents->get((string) $day, collect()) as $event)
                                    <a href="{{ route('admin.events.registrations', $event) }}"
                                       class="block truncate rounded px-1.5 py-0.5 text-xs"
                                       style="background: {{ $event->status === 'cancelled' ? 'var(--color-state-danger)' : 'var(--color-brand-500)' }}; color: #04201c"
                                       title="{{ $event->title_ar }} — {{ $event->starts_at?->format('H:i') }}">
                                        {{ $event->starts_at?->format('H:i') }} {{ $event->title_ar }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            @if ($calendarEvents->isEmpty())
                <p class="text-xs mt-3" style="color: var(--text-muted)">{{ setting('admin.events.index.mfysh_faalyat_fy_alshhr_dh', 'مفيش فعاليّات في الشهر ده.') }}</p>
            @endif
        </div>
    @elseif ($events->isEmpty())
        <x-empty :message="setting('events.empty_message', 'لا فعاليّات — أنشئ أوّل لقاء.')" />
    @else
        {{--
          ⭐ [2026-09-10] «القائمة جدولًا لا كروتًا» (12.11: الغلاف · العنوان ·
          النوع · التاريخ · السعة/المسجّلون · السعر · الحالة · إجراءات) — كانت
          كروتًا بلا عمودَي الغلاف والسعر رغم أنّ `cover_path`/`price_coins`/
          `price_tickets` حقولٌ محفوظةٌ بالفعل. جدولٌ على الديسكتوب وكروتٌ
          رأسيّة على الموبايل بلا تمرير أفقيّ (2.15-ج)، على نمط
          `registrations-index.blade.php`.
        --}}
        <div class="card p-0 overflow-hidden hidden md:block min-w-0">
            <div class="overflow-x-auto min-w-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background: var(--surface-sunken)">
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.events.index.col_ghlaf', 'الغلاف') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.events.index.col_alanwan', 'العنوان') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.events.index.alnwa', 'النوع') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.events.index.col_altarykh', 'التاريخ') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.events.index.col_alsaa_almsjlwn', 'السعة/المسجّلون') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.events.index.col_alsar', 'السعر') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.events.index.alhala', 'الحالة') }}</th>
                            <th class="text-start px-4 py-3 font-semibold">{{ setting('admin.events.index.col_ijraat', 'إجراءات') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            @php
                                $registered = $event->registrations_count;
                                $capacity = $event->capacity ?: 0;
                                $percent = $capacity ? min(100, round($registered / $capacity * 100)) : 0;
                                $free = (float) $event->price_coins <= 0 && (float) $event->price_tickets <= 0;
                            @endphp
                            <tr style="border-top: 1px solid var(--border)">
                                <td class="px-4 py-3">
                                    @if ($event->cover_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($event->cover_path) }}" alt=""
                                             class="rounded-lg object-cover" style="width: 44px; height: 44px" loading="lazy">
                                    @else
                                        <span class="inline-flex items-center justify-center rounded-lg"
                                              style="width: 44px; height: 44px; background: var(--surface-sunken); color: var(--color-brand-500)">
                                            <x-icon name="event" size="20" />
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-semibold min-w-0">
                                    {{ $event->title_ar }}
                                    @if ($event->attendance_code)
                                        <span class="block text-xs font-normal font-mono" style="color: var(--text-muted)">{{ setting('admin.events.index.kwd_alhdwr', '· كود الحضور') }} {{ $event->attendance_code }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs">{{ $modes[$event->mode] ?? $event->mode }}</td>
                                <td class="px-4 py-3 text-xs" title="{{ $event->starts_at?->format('Y-m-d H:i') }}">{{ $event->starts_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2" style="min-width: 7rem">
                                        <div class="h-1.5 flex-1 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                                            <div class="h-full" style="width: {{ $percent }}%; background: var(--color-brand-500)"></div>
                                        </div>
                                        <span class="text-xs shrink-0" style="color: var(--text-muted)">{{ $registered }}{{ $capacity ? '/'.$capacity : '' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    @if ($free)
                                        {{ setting('admin.events.index.mjanya', 'مجّانيّة') }}
                                    @else
                                        {{ trim(((float) $event->price_coins > 0 ? (int) $event->price_coins.' '.setting('admin.events.index.kwynz', 'كوينز').' ' : '').((float) $event->price_tickets > 0 ? (int) $event->price_tickets.' '.setting('admin.events.index.tdhkra', 'تذكرة') : '')) }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-state-badge :state="match ($event->status) { 'published' => 'ok', 'cancelled' => 'danger', default => 'idle' }"
                                                   :label="match ($event->status) { 'published' => setting('admin.events.index.mnshwra', 'منشورة'), 'cancelled' => setting('admin.events.index.mlghaa', 'ملغاة'), default => setting('admin.events.index.mswda', 'مسودّة') }" />
                                </td>
                                <td class="px-4 py-3">
                                    @include('admin.events.partials.event-actions', ['event' => $event])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- الموبايل: كروت رأسيّة — بلا تمرير أفقيّ (2.15-ج) --}}
        <section class="md:hidden space-y-3">
            @foreach ($events as $event)
                @php
                    $registered = $event->registrations_count;
                    $capacity = $event->capacity ?: 0;
                    $percent = $capacity ? min(100, round($registered / $capacity * 100)) : 0;
                    $free = (float) $event->price_coins <= 0 && (float) $event->price_tickets <= 0;
                @endphp

                <article class="card p-4">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0 flex items-start gap-3">
                            @if ($event->cover_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($event->cover_path) }}" alt=""
                                     class="rounded-lg object-cover shrink-0" style="width: 44px; height: 44px" loading="lazy">
                            @else
                                <span class="inline-flex items-center justify-center rounded-lg shrink-0"
                                      style="width: 44px; height: 44px; background: var(--surface-sunken); color: var(--color-brand-500)">
                                    <x-icon name="event" size="20" />
                                </span>
                            @endif
                            <div class="min-w-0">
                                <div class="font-semibold">{{ $event->title_ar }}</div>
                                <div class="text-xs mt-0.5" style="color: var(--text-muted)"
                                     title="{{ $event->starts_at?->format('Y-m-d H:i') }}">
                                    {{ $modes[$event->mode] ?? $event->mode }} · {{ $event->starts_at?->diffForHumans() }}
                                    @if ($event->attendance_code) {{ setting('admin.events.index.kwd_alhdwr', '· كود الحضور') }} <code>{{ $event->attendance_code }}</code> @endif
                                </div>
                            </div>
                        </div>

                        <x-state-badge :state="match ($event->status) { 'published' => 'ok', 'cancelled' => 'danger', default => 'idle' }"
                                       :label="match ($event->status) { 'published' => setting('admin.events.index.mnshwra', 'منشورة'), 'cancelled' => setting('admin.events.index.mlghaa', 'ملغاة'), default => setting('admin.events.index.mswda', 'مسودّة') }" />
                    </div>

                    <div class="flex items-center gap-2 mt-3">
                        <div class="h-1.5 flex-1 rounded-full overflow-hidden" style="background: var(--surface-sunken)">
                            <div class="h-full" style="width: {{ $percent }}%; background: var(--color-brand-500)"></div>
                        </div>
                        <span class="text-xs shrink-0" style="color: var(--text-muted)">
                            {{ $registered }}{{ $capacity ? '/'.$capacity : '' }} {{ setting('admin.events.index.msjl', 'مسجّل') }}
                            @if (! $free)
                                · {{ trim(((float) $event->price_coins > 0 ? (int) $event->price_coins.' '.setting('admin.events.index.kwynz', 'كوينز').' ' : '').((float) $event->price_tickets > 0 ? (int) $event->price_tickets.' '.setting('admin.events.index.tdhkra', 'تذكرة') : '')) }}
                            @else
                                · {{ setting('admin.events.index.mjanya', 'مجّانيّة') }}
                            @endif
                        </span>
                    </div>

                    <div class="mt-3">
                        @include('admin.events.partials.event-actions', ['event' => $event])
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    @can('events.manage')
        @include('admin.volunteer.partials.settings-card', [
            'title' => setting('admin.events.index.iadadat_alfaalyat', 'إعدادات الفعاليّات'),
            'rows' => $settings,
            'action' => route('admin.events.settings.save'),
            'lockedKeys' => ['events.attendance_code_persistent'],
        ])
    @endcan
@endsection

@section('mobile_action')
    @can('events.create')
        <button type="button" data-modal-open="event-modal"
                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.events.index.faalya_2', '+ فعاليّة') }}</button>
    @endcan
@endsection

@push('modals')
    @can('events.create')
        <x-modal id="event-modal" :title="setting('admin.events.index.faalya', 'فعاليّة')">
            <form method="post" action="{{ route('admin.events.save') }}">
                @csrf
                <input type="hidden" name="id" id="ev-id">

                {{-- ⭐ الفورم الأطول من حدّ الإعدادات يتقسّم خطوات بحفظ تلقائيّ بينها (2.15-ب) --}}
                <x-form.stepper id="event-form" :labels="[setting('admin.events.index.alasasyat', 'الأساسيّات'), setting('admin.events.index.altfasyl', 'التفاصيل'), setting('admin.events.index.alajnda', 'الأجندة')]">
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.alanwan_arby', 'العنوان (عربيّ)') }}
                        <input type="text" name="title_ar" id="ev-title" required maxlength="180"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.alanwan_injlyzy', 'العنوان (إنجليزيّ)') }}
                        <input type="text" name="title_en" id="ev-title-en" maxlength="180"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                <div class="grid sm:grid-cols-2 gap-3 mt-3">
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.alwsf_arby', 'الوصف (عربيّ)') }}
                        <textarea name="description" rows="2" maxlength="4000"
                                  class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.alwsf_injlyzy', 'الوصف (إنجليزيّ)') }}
                        <textarea name="description_en" rows="2" maxlength="4000"
                                  class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    </label>
                </div>

                <div class="grid sm:grid-cols-3 gap-3 mt-3">
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.alnwa', 'النوع') }}
                        <select name="mode" id="ev-mode" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($modes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.ybda', 'يبدأ') }}
                        <input type="datetime-local" name="starts_at" id="ev-starts" required
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.ynthy_akhtyary', 'ينتهي (اختياريّ)') }}
                        <input type="datetime-local" name="ends_at"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                <div class="grid sm:grid-cols-2 gap-3 mt-3">
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.almkan_llawflayn_walhjyn', 'المكان (للأوفلاين والهجين)') }}
                        <input type="text" name="location" id="ev-location" maxlength="255"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.rabt_alandmam_llawnlayn_walhjyn', 'رابط الانضمام (للأونلاين والهجين)') }}
                        <input type="url" name="join_link" id="ev-join" maxlength="255"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                {{--
                  ⭐ [2026-09-10] «المكان + الخريطة» (12.11) — العمودان lat/lng
                  محفوظان في المايجريشن ومحوَّلان بالفعل في الموديل، وصفحة
                  الفعاليّة العامّة تعرض بالفعل رابط خريطة OpenStreetMap منهما
                  (events/show.blade.php)، فالناقص كان حقلَي الإدخال هنا فقط.
                  اختياريّان دومًا — غيابهما لا يُسقِط الحفظ ولا يمنع رابط الخريطة
                  من الاختفاء بأمان في الصفحة العامّة.
                --}}
                <div class="grid sm:grid-cols-2 gap-3 mt-3">
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.kht_alard', 'خط العرض') }}
                        <input type="number" step="0.0000001" min="-90" max="90" name="lat" id="ev-lat"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.kht_altwl', 'خط الطول') }}
                        <input type="number" step="0.0000001" min="-180" max="180" name="lng" id="ev-lng"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <span class="block text-xs sm:col-span-2" style="color: var(--text-muted)">{{ setting('admin.events.index.alihdathyat_hint', 'اختياريّ — تُستخدَم لعرض رابط خريطة في صفحة الفعاليّة العامّة (أوفلاين/هجين).') }}</span>
                </div>

                <div class="grid sm:grid-cols-3 gap-3 mt-3">
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.rabt_altsjyl_alkharjy', 'رابط التسجيل الخارجيّ') }}
                        <input type="url" name="registration_link" id="ev-registration" maxlength="255"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.rabt_altsjyl_bad_alantha', 'رابط التسجيل بعد الانتهاء') }}
                        <input type="url" name="recording_link" maxlength="255"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.alsaa', 'السعة') }}
                        <input type="number" min="1" name="capacity" id="ev-capacity"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                {{-- ⭐ السعر: مجّانيّ/كوينز/تذكرة + كوبون (12.11) — كان مُتحقَّقًا منه
                     خادميًّا ومقروءًا في صفحة المستخدم وبلا أيّ حقل هنا، فلا سبيل
                     لضبطه إلّا بتعديل قاعدة البيانات باليد. --}}
                <div class="grid sm:grid-cols-3 gap-3 mt-3">
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.alsar_kwynz', 'السعر (كوينز)') }}
                        <input type="number" min="0" step="1" name="price_coins" id="ev-price-coins" value="0"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ setting('admin.events.index.sfr_mjanya', 'صفر = مجّانيّة') }}</span>
                    </label>
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.alsar_tdhakr', 'السعر (تذاكر)') }}
                        <input type="number" min="0" step="1" name="price_tickets" id="ev-price-tickets" value="0"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.kwbwn_khsm_akhtyary', 'كوبون خصم (اختياريّ)') }}
                        <select name="coupon_id" id="ev-coupon" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">{{ setting('admin.events.index.bla_kwbwn', '— بلا كوبون') }}</option>
                            @foreach ($coupons as $coupon)
                                <option value="{{ $coupon->id }}">{{ $coupon->code }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                {{-- الغلاف (12.11) — العمود كان موجودًا وميّتًا بلا حقل يكتبه --}}
                <label class="block text-sm font-semibold mt-3">{{ setting('admin.events.index.ghlaf_alfaalya', 'غلاف الفعاليّة') }}
                    <input type="text" name="cover_path" id="ev-cover" maxlength="255"
                           placeholder="{{ setting('admin.events.index.msar_alswra_mn_mktba_alwsayt', 'مسار الصورة من مكتبة الوسائط') }}"
                           class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <div class="grid sm:grid-cols-3 gap-3 mt-3">
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.kwd_alhdwr_otp_rqmy', 'كود الحضور (OTP رقميّ)') }}
                        <input type="text" name="attendance_code" id="ev-code" maxlength="32"
                               placeholder="{{ setting('admin.events.index.ywld_tlqayya_lw_fady', 'يُولَّد تلقائيًّا لو فاضي') }}"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1 font-mono"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    {{-- نوع شهادة الحضور يُضبَط في «إدارة الشهادات» لا هنا (13.3 · 12.5) --}}
                    <div class="text-sm font-semibold">{{ setting('admin.events.index.nwa_shhada_alhdwr', 'نوع شهادة الحضور') }}
                        <p class="mt-1 rounded-xl px-3 py-2 text-xs font-normal leading-relaxed"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text-muted)">
                            {{ setting('admin.events.index.bytzbt_mn', 'بيتظبط من') }} <strong>{{ setting('admin.events.index.idara_alshhadat', 'إدارة الشهادات') }}</strong> {!! strtr(setting('admin.events.index.alnwa_almrbwt_balfaalyat_hw_almftah_v1_way', '— النوع المربوط بالفعاليّات هو المفتاح «:v1»، وأيّ تعديل عليه بيسري على كلّ الفعاليّات.'), [':v1' => e(setting('events.certificate.default_type_key', 'event'))]) !!}
                        </p>
                    </div>
                    <label class="text-sm font-semibold">{{ setting('admin.events.index.alhala', 'الحالة') }}
                        <select name="status" id="ev-status" class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="draft">{{ setting('admin.events.index.mswda', 'مسودّة') }}</option>
                            <option value="published">{{ setting('admin.events.index.mnshwra', 'منشورة') }}</option>
                        </select>
                    </label>
                </div>

                {{--
                  «تذكيرات مجدولة» في فورم الفعاليّة (12.11) — والمواعيد نفسها
                  قائمةٌ في بلوك إعدادات الفعاليّات (24.3)، فهنا مفتاح تشغيلها
                  لهذه الفعاليّة وحدها. والنصّ من الإعدادات لا محروقًا (2.13).
                --}}
                <label class="flex items-center gap-2 text-sm font-semibold mt-3">
                    <input type="checkbox" name="reminders_enabled" id="ev-reminders" value="1" checked
                           style="min-width: 20px; min-height: 20px">
                    {{ setting('events.reminder.form_label', 'تذكيرات مجدولة للمسجّلين') }}
                    <span class="text-xs font-normal" style="color: var(--text-muted)">
                        ({{ setting('events.reminder.form_hint', 'بتتبع مواعيد التذكير في إعدادات الفعاليّات') }})
                    </span>
                </label>

                {{-- جدول المكافأة المتدرّجة زمنيًّا (13.3) --}}
                <details class="mt-3 rounded-xl p-3" style="background: var(--surface-sunken)">
                    <summary class="cursor-pointer text-sm font-semibold select-none">{{ setting('admin.events.index.almkafaa_almtdrja_zmnya', 'المكافأة المتدرّجة زمنيًّا') }}</summary>
                    <div class="space-y-2 mt-2">
                        @foreach ($defaultTiers as $i => $tier)
                            <div class="grid grid-cols-3 gap-2">
                                <label class="text-xs">{{ setting('admin.events.index.khlal_saaa', 'خلال (ساعة)') }}
                                    <input type="number" min="1" name="reward_tiers[{{ $i }}][hours]" value="{{ $tier['hours'] ?? '' }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1"
                                           style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                                <label class="text-xs">XP
                                    <input type="number" min="0" name="reward_tiers[{{ $i }}][xp]" value="{{ $tier['xp'] ?? 0 }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1"
                                           style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                                <label class="text-xs">{{ setting('admin.events.index.tdhakr', 'تذاكر') }}
                                    <input type="number" min="0" name="reward_tiers[{{ $i }}][tickets]" value="{{ $tier['tickets'] ?? 0 }}"
                                           class="w-full rounded-lg px-2 py-1.5 mt-1"
                                           style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                </label>
                            </div>
                        @endforeach
                    </div>
                </details>

                {{-- الأجندة والمتحدّثون --}}
                <details class="mt-2 rounded-xl p-3" style="background: var(--surface-sunken)">
                    <summary class="cursor-pointer text-sm font-semibold select-none">{{ setting('admin.events.index.alajnda_walmthdthwn', 'الأجندة والمتحدّثون') }}</summary>
                    <div class="space-y-2 mt-2">
                        @for ($i = 0; $i < 4; $i++)
                            <div class="grid grid-cols-3 gap-2">
                                <input type="text" name="agenda[{{ $i }}][title]" placeholder="{{ setting('admin.events.index.anwan_aljlsa', 'عنوان الجلسة') }}" maxlength="180"
                                       class="rounded-lg px-2 py-1.5" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                <input type="text" name="agenda[{{ $i }}][speaker]" placeholder="{{ setting('admin.events.index.almthdth', 'المتحدّث') }}" maxlength="120"
                                       class="rounded-lg px-2 py-1.5" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                                <input type="datetime-local" name="agenda[{{ $i }}][starts_at]"
                                       class="rounded-lg px-2 py-1.5" style="background: var(--surface); border: 1px solid var(--border); color: var(--text)">
                            </div>
                        @endfor
                    </div>
                </details>

                </x-form.stepper>

                <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.events.index.ahfz_alfaalya', 'احفظ الفعاليّة') }}</button>
            </form>
        </x-modal>
    @endcan
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('[data-event-edit]').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById('ev-id').value = btn.dataset.id;
                document.getElementById('ev-title').value = btn.dataset.title;
                document.getElementById('ev-title-en').value = btn.dataset.titleEn || '';
                document.getElementById('ev-mode').value = btn.dataset.mode;
                document.getElementById('ev-starts').value = btn.dataset.starts || '';
                document.getElementById('ev-capacity').value = btn.dataset.capacity || '';
                document.getElementById('ev-code').value = btn.dataset.code || '';
                document.getElementById('ev-location').value = btn.dataset.location || '';
                document.getElementById('ev-lat').value = btn.dataset.lat || '';
                document.getElementById('ev-lng').value = btn.dataset.lng || '';
                document.getElementById('ev-join').value = btn.dataset.join || '';
                document.getElementById('ev-registration').value = btn.dataset.registration || '';
                document.getElementById('ev-price-coins').value = btn.dataset.priceCoins || 0;
                document.getElementById('ev-price-tickets').value = btn.dataset.priceTickets || 0;
                document.getElementById('ev-coupon').value = btn.dataset.coupon || '';
                document.getElementById('ev-cover').value = btn.dataset.cover || '';
                document.getElementById('ev-status').value = btn.dataset.status;
                document.getElementById('ev-reminders').checked = btn.dataset.reminders !== '0';
                const modal = document.getElementById('event-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        });
    </script>
@endpush
