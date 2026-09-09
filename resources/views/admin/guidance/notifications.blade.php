@extends('layouts.admin')

@section('title', setting('admin.guidance.notifications.alishaarat', 'الإشعارات'))

@section('content')
    {{-- الإشعارات (12.6-ب): أنواع + إرسال يدويّ برابط أو بدون + تجميع المتشابهة --}}
    <x-page-header
        :title="setting('admin.guidance.notifications.alishaarat', 'الإشعارات')"
        :subtitle="setting('admin.guidance.notifications.adbt_alanwaa_wabat_ishaara_ydwya_ljmhwr_mhdd', 'اضبط الأنواع، وابعت إشعارًا يدويًّا لجمهور محدَّد.')"
        :breadcrumbs="[['label' => setting('admin.guidance.notifications.altwjyh_waldam', 'التوجيه والدعم'), 'url' => route('admin.guidance.index')], ['label' => setting('admin.guidance.notifications.alishaarat', 'الإشعارات')]]">
        <x-slot:action>
            <div class="flex items-center gap-2 flex-wrap">
                @can('announcements.create')
                    <button type="button" data-modal-open="manual-notification"
                            class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.notifications.irsal_ishaar_ydwy', 'إرسال إشعار يدويّ') }}</button>
                @endcan
                {{-- الهيدر المنصوص (24.3 سطر 5067): «قوالب البريد» · «معاينة الجرس» --}}
                @can('email_templates.list')
                    <a href="{{ route('admin.guidance.email-templates.index') }}"
                       class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                       style="background: var(--surface-raised)">{{ setting('admin.guidance.notifications.qwalb_albryd', 'قوالب البريد') }}</a>
                @endcan
                <button type="button" data-modal-open="bell-preview"
                        class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--surface-raised)">{{ setting('admin.guidance.notifications.maayna_aljrs', 'معاينة الجرس') }}</button>
            </div>
        </x-slot:action>
    </x-page-header>

    <x-tabs :tabs="$tabs" current="notifications" />

    {{-- مصفوفة النوع × القناة (24.3) --}}
    <div class="card p-4">
        <h2 class="font-bold mb-3">{{ setting('admin.guidance.notifications.anwaa_alishaarat_wqnwatha', 'أنواع الإشعارات وقنواتها') }}</h2>

        @can('notifications.manage')
            <form method="post" action="{{ route('admin.guidance.notifications.matrix.save') }}">
                @csrf
                <div class="space-y-2">
                    @foreach ($types as $key => $label)
                        <div class="flex items-center gap-3 flex-wrap py-2" style="border-top: 1px solid var(--border)">
                            <span class="flex-1 text-sm">{{ $label }}</span>
                            @foreach ($channels as $channelKey => $channelLabel)
                                <label class="flex items-center gap-1 text-xs">
                                    <input type="checkbox" name="matrix[{{ $key }}][{{ $channelKey }}]" value="1"
                                           @checked(setting('notifications.matrix.'.$key.'.'.$channelKey, $channelKey === 'bell'))>
                                    {{ $channelLabel }}
                                </label>
                            @endforeach
                            {{-- عمودا «نصّ القالب»/«مفعّل» (24.3 سطر 5069) — من نفس جدول email_templates --}}
                            @php $rowTemplate = $emailTemplates->get($key); @endphp
                            <span class="flex items-center gap-1 text-xs" style="color: {{ $rowTemplate?->is_enabled ? 'var(--color-state-ok)' : 'var(--text-muted)' }}">
                                {{ $rowTemplate?->is_enabled ? '✓' : '—' }} {{ setting('admin.guidance.notifications.mfaal', 'مفعّل') }}
                            </span>
                            @can('email_templates.edit')
                                <button type="button" data-modal-open="template-{{ $key }}" class="text-xs underline">
                                    {{ setting('admin.guidance.notifications.ns_alqalb', 'نصّ القالب') }}
                                </button>
                            @endcan
                        </div>
                    @endforeach
                </div>

                <button class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard mt-3"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.notifications.hfz_almsfwfa', 'احفظ المصفوفة') }}</button>
            </form>
        @else
            {{-- بلا صلاحيّة الحفظ: عرضُ حالةٍ لا عنصر تحكّمٍ يبدو تفاعليًّا (2.15-أ-7: المحظور يُخفى لا يُعطَّل) --}}
            <div class="space-y-2">
                @foreach ($types as $key => $label)
                    <div class="flex items-center gap-3 flex-wrap py-2" style="border-top: 1px solid var(--border)">
                        <span class="flex-1 text-sm">{{ $label }}</span>
                        @foreach ($channels as $channelKey => $channelLabel)
                            @php $on = (bool) setting('notifications.matrix.'.$key.'.'.$channelKey, $channelKey === 'bell'); @endphp
                            <span class="flex items-center gap-1 text-xs" style="color: {{ $on ? 'var(--color-state-ok)' : 'var(--text-muted)' }}">
                                {{ $on ? '✓' : '—' }} {{ $channelLabel }}
                            </span>
                        @endforeach
                        @php $rowTemplate = $emailTemplates->get($key); @endphp
                        <span class="flex items-center gap-1 text-xs" style="color: {{ $rowTemplate?->is_enabled ? 'var(--color-state-ok)' : 'var(--text-muted)' }}">
                            {{ $rowTemplate?->is_enabled ? '✓' : '—' }} {{ setting('admin.guidance.notifications.mfaal', 'مفعّل') }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endcan

        @can('email_templates.edit')
            @foreach ($types as $key => $label)
                @php $rowTemplate = $emailTemplates->get($key); @endphp
                <x-modal :id="'template-'.$key" :title="setting('admin.guidance.notifications.ns_alqalb', 'نصّ القالب').' — '.$label">
                    <form id="template-form-{{ $key }}" method="post"
                          action="{{ route('admin.guidance.notifications.matrix.template', $key) }}" class="space-y-3">
                        @csrf
                        <x-form.input name="subject" :label="setting('admin.guidance.email_templates.aleenwan', 'عنوان الرسالة (اختياريّ)')" :value="$rowTemplate?->subject" />

                        <label class="block">
                            <span class="block text-sm mb-1">{{ setting('admin.guidance.email_templates.mhtwa_alqalb', 'محتوى القالب') }}</span>
                            <textarea name="body" rows="4" required class="w-full rounded-xl px-3 py-2 text-sm"
                                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $rowTemplate?->body }}</textarea>
                            <span class="block text-xs mt-1" style="color: var(--text-muted)">
                                {{ setting('admin.guidance.email_templates.wswm_mtaha', 'الوسوم المتاحة:') }} {{ implode(' · ', array_keys(\App\Services\Notifications\AnnouncementPersonalizer::tokens())) }}
                            </span>
                        </label>

                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="is_enabled" value="0">
                            <input type="checkbox" name="is_enabled" value="1" @checked($rowTemplate?->is_enabled)>
                            {{ setting('admin.guidance.notifications.mfaal', 'مفعّل') }}
                        </label>
                    </form>

                    <x-slot:footer>
                        <button type="submit" form="template-form-{{ $key }}"
                                class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.email_templates.hfz', 'احفظ') }}</button>
                    </x-slot:footer>
                </x-modal>
            @endforeach
        @endcan

        {{-- حدّ الهدوء كما يُطبَّق فعلًا في الخادم (12.6-ب) — لا وعدًا على الشاشة --}}
        <p class="text-xs mt-3" style="color: var(--text-muted)">
            {!! strtr(setting('admin.guidance.notifications.hd_alhdw_v1_ishaarat_llmstkhdm_fy_alywm', 'حدّ الهدوء: :v1 إشعارات للمستخدم في اليوم — والزيادة تتجمّع في إشعار واحد بدل ما تنهال عليه.'), [':v1' => e($rateLimit)]) !!}
            @if (! empty($quietLimit['exempt']))
                <br>{{ setting('admin.guidance.notifications.mstthnaa_mn_alhd_btwsl_dayma', 'مستثناة من الحدّ (بتوصل دايمًا):') }} {{ implode(' · ', array_map(fn ($k) => $types[$k] ?? $k, $quietLimit['exempt'])) }}.
            @endif
            <br>{!! strtr(setting('admin.guidance.notifications.atjma_alywm_bsbb_alhd_v1_ishaara', 'اتجمّع اليوم بسبب الحدّ: :v1 إشعارًا.'), [':v1' => e((int) ($quietLimit['deferred_today'] ?? 0))]) !!}
        </p>
    </div>

    {{-- تجميع الإشعارات المتشابهة في إشعار واحد بدل الإغراق (12.6-ب) --}}
    <div class="card p-4 mt-4">
        <h2 class="font-bold mb-2">{{ setting('admin.guidance.notifications.altjmya_alhaly', 'التجميع الحاليّ') }}</h2>
        @if ($grouping->isEmpty())
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.guidance.notifications.mfysh_ishaarat_atjmat_mwkhra', 'مفيش إشعارات اتجمّعت مؤخّرًا.') }}</p>
        @else
            <ul class="text-sm space-y-1">
                @foreach ($grouping as $group)
                    <li>{{ $types[$group->category] ?? $group->category }}: {{ (int) $group->total }} {!! strtr(setting('admin.guidance.notifications.ishaara_atjmawa_fy_v1_ishaar', 'إشعارًا اتجمّعوا في :v1 إشعار'), [':v1' => e((int) $group->rows)]) !!}</li>
                @endforeach
            </ul>
        @endif
    </div>

    @can('announcements.create')
        <x-modal id="manual-notification" :title="setting('admin.guidance.notifications.ishaar_ydwy', 'إشعار يدويّ')">
            <form method="post" action="{{ route('admin.guidance.notifications.send') }}" class="space-y-3">
                @csrf

                <x-form.input name="title" :label="setting('admin.guidance.notifications.alanwan', 'العنوان')" required />

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.guidance.notifications.alns', 'النصّ') }}</span>
                    <textarea name="body" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                {{-- برابط أو بدون (12.6-ب) --}}
                <x-form.input name="url" :label="setting('admin.guidance.notifications.rabt_akhtyary', 'رابط (اختياريّ)')" :hint="setting('admin.guidance.notifications.sybh_fady_lw_alishaar_bla_wjha', 'سيبه فاضي لو الإشعار بلا وجهة.')" />

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.guidance.notifications.alnwa', 'النوع') }}</span>
                    <select name="category" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="block text-sm mb-1">{{ setting('admin.guidance.notifications.aljmhwr', 'الجمهور') }}</span>
                    <select name="audience_type" data-audience class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <option value="all">{{ setting('admin.guidance.notifications.alkl', 'الكلّ') }}</option>
                        <option value="role">{{ setting('admin.guidance.notifications.hsb_aldwr', 'حسب الدور') }}</option>
                        <option value="course">{{ setting('admin.guidance.notifications.hsb_altdryb', 'حسب التدريب') }}</option>
                        <option value="path">{{ setting('admin.guidance.notifications.hsb_almsar', 'حسب المسار') }}</option>
                    </select>
                </label>

                <div class="hidden" data-audience-panel="role">
                    <select name="audience_keys[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($audiences['roles'] as $role)
                            <option value="{{ $role->key }}">{{ $role->name_ar }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="hidden" data-audience-panel="course">
                    <select name="audience_ids[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($audiences['courses'] as $course)
                            <option value="{{ $course->id }}">{{ $course->name_ar }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="hidden" data-audience-panel="path">
                    <select name="audience_ids[]" multiple size="4" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        @foreach ($audiences['paths'] as $path)
                            <option value="{{ $path->id }}">{{ $path->name_ar }}</option>
                        @endforeach
                    </select>
                </div>

                <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.guidance.notifications.abat', 'ابعت') }}</button>
            </form>
        </x-modal>
    @endcan

    {{-- ⭐ معاينة الجرس (24.3 سطر 5067) --}}
    <x-modal id="bell-preview" :title="setting('admin.guidance.notifications.maayna_aljrs', 'معاينة الجرس')">
        <label class="block mb-3">
            <span class="block text-sm mb-1">{{ setting('admin.guidance.notifications.alnwa', 'النوع') }}</span>
            <select data-bell-preview-category class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div data-bell-preview-body></div>
    </x-modal>

    @include('admin.courses.partials.toast')

    @push('scripts')
        <script>
            const audience = document.querySelector('[data-audience]');
            audience?.addEventListener('change', () => {
                document.querySelectorAll('[data-audience-panel]').forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.audiencePanel !== audience.value);
                });
            });

            // ⭐ معاينة الجرس (24.3 سطر 5067): جزءٌ يُجلَب لنوعٍ بعينه بدل صفحةٍ كاملة
            const bellPreviewSelect = document.querySelector('[data-bell-preview-category]');
            const bellPreviewBody = document.querySelector('[data-bell-preview-body]');

            const loadBellPreview = () => {
                if (! bellPreviewSelect || ! bellPreviewBody) return;

                fetch('{{ route('admin.guidance.notifications.bell-preview') }}?category=' + encodeURIComponent(bellPreviewSelect.value), {
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                })
                    .then((response) => response.text())
                    .then((html) => { bellPreviewBody.innerHTML = html; });
            };

            bellPreviewSelect?.addEventListener('change', loadBellPreview);
            document.querySelector('[data-modal-open="bell-preview"]')?.addEventListener('click', loadBellPreview);
        </script>
    @endpush
@endsection
