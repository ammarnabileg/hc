{{-- الشارات (7.4): إضافة/تعديل + **شرط الفتح مكتوب صراحةً** + الأيقونة --}}

<section class="card p-4 md:p-5">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
        <h2 class="font-bold">{{ setting('admin.gamification.tabs.badges.bnk_alsharat', 'بنك الشارات') }}</h2>
        @can('badges.create')
            <button type="button" data-modal-open="badge-modal"
                    class="btn rounded-xl px-3 py-1.5 text-xs font-semibold"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.gamification.tabs.badges.shara_2', '+ شارة') }}</button>
        @endcan
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @forelse ($data['badges'] as $badge)
            <article class="rounded-xl p-3" style="background: var(--surface-sunken)">
                <div class="flex items-start gap-3">
                    {{-- أيقونة SVG بهويّة المنصّة — بلا أيّ مكتبة أيقونات (2.16-ج) --}}
                    <span class="shrink-0 inline-flex items-center justify-center rounded-xl"
                          style="width: 42px; height: 42px; background: var(--surface); border: 1px solid var(--border)">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                             style="color: var(--color-brand-500)">
                            <circle cx="12" cy="9" r="5" />
                            <path d="M8.5 13.5 7 21l5-2.5L17 21l-1.5-7.5" />
                        </svg>
                    </span>

                    <div class="min-w-0">
                        <div class="font-semibold text-sm">{{ $badge->name_ar }}</div>
                        {{-- شرط الفتح مكتوب صراحةً — لا ألغاز --}}
                        <p class="text-xs mt-1" style="color: var(--text-muted)">{{ $badge->condition_text_ar }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 mt-3">
                    <x-state-badge :state="$badge->is_active ? 'ok' : 'idle'" :label="$badge->is_active ? setting('admin.gamification.tabs.badges.mfala', 'مفعّلة') : setting('admin.gamification.tabs.badges.mwqwfa', 'موقوفة')" />
                    <span class="flex items-center gap-2">
                        @can('badges.edit')
                            <button type="button" class="text-xs underline" data-badge-edit
                                    data-id="{{ $badge->id }}" data-key="{{ $badge->key }}"
                                    data-name="{{ $badge->name_ar }}" data-name-en="{{ $badge->name_en }}"
                                    data-description="{{ $badge->description_ar }}"
                                    data-condition="{{ $badge->condition_text_ar }}"
                                    data-ckey="{{ $badge->condition_key }}" data-cvalue="{{ $badge->condition_value }}"
                                    data-icon="{{ $badge->icon_path }}">{{ setting('admin.gamification.tabs.badges.tadyl', 'تعديل') }}</button>
                        @endcan
                        @can('badges.delete')
                            <form method="post" action="{{ route('admin.gamification.badges.delete', $badge) }}"
                                  onsubmit="return confirm('{{ setting('admin.gamification.tabs.badges.thdhf_alshara_dy', 'تحذف الشارة دي؟') }}')">
                                @csrf
                                <button type="submit" class="text-xs underline" style="color: var(--color-state-danger)">{{ setting('admin.gamification.tabs.badges.hdhf', 'حذف') }}</button>
                            </form>
                        @endcan
                    </span>
                </div>
            </article>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <x-empty :message="setting('admin.gamification.tabs.badges.la_sharat_bad_adf_awl_shara', 'لا شارات بعد — أضف أوّل شارة.')" />
            </div>
        @endforelse
    </div>
</section>

@can('badges.edit')
    @include('admin.volunteer.partials.settings-card', [
        'title' => setting('admin.gamification.tabs.badges.iadadat_alsharat', 'إعدادات الشارات'),
        'rows' => $data['settings'],
        'action' => route('admin.gamification.settings.save'),
        'resetAction' => route('admin.gamification.reset'),
        'resetPayload' => ['group' => 'gamification_badges'],
    ])
@endcan

@push('modals')
    @can('badges.edit')
        <x-modal id="badge-modal" :title="setting('admin.gamification.tabs.badges.shara', 'شارة')">
            <form method="post" action="{{ route('admin.gamification.badges.save') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="id" id="badge-id">

                <label class="block text-sm font-semibold mb-1" for="badge-key">{{ setting('admin.gamification.tabs.badges.almftah', 'المفتاح') }}</label>
                <input type="text" name="key" id="badge-key" required maxlength="64"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3 font-mono"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                {{-- التسمية ثنائيّة اللغة قاعدة عامّة تشمل الشارات (القسم 3) --}}
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <label class="text-sm font-semibold">{{ setting('admin.gamification.tabs.badges.alasm_arby', 'الاسم (عربيّ)') }}
                        <input type="text" name="name_ar" id="badge-name" required maxlength="120"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                    <label class="text-sm font-semibold">{{ setting('admin.gamification.tabs.badges.alasm_injlyzy', 'الاسم (إنجليزيّ)') }}
                        <input type="text" name="name_en" id="badge-name-en" required maxlength="120" dir="ltr"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>

                {{-- 7.4: «لكلّ شارة اسم + **وصف** + صورة» — والوصف غير شرط الفتح --}}
                <label class="block text-sm font-semibold mb-1" for="badge-description">{{ setting('admin.gamification.tabs.badges.alwsf_ma_mana_alshara', 'الوصف — ما معنى الشارة') }}</label>
                <textarea name="description_ar" id="badge-description" rows="2" maxlength="500"
                          placeholder="{{ setting('admin.gamification.tabs.badges.mthal_lashab_alkhtwa_alawla_albdaya_asab_ma', 'مثال: لأصحاب الخطوة الأولى — البداية أصعب ما في الطريق.') }}"
                          class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>

                <label class="block text-sm font-semibold mb-1" for="badge-condition">{{ setting('admin.gamification.tabs.badges.shrt_alfth_mktwb_sraha', 'شرط الفتح — مكتوب صراحةً') }}</label>
                <input type="text" name="condition_text_ar" id="badge-condition" required maxlength="255"
                       placeholder="{{ setting('admin.gamification.tabs.badges.mthal_akml_10_drws_fy_asbwa_wahd', 'مثال: أكمل 10 دروس في أسبوع واحد.') }}"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-3"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">

                <div class="grid grid-cols-2 gap-3 mb-1">
                    {{-- ⭐ قائمة مقفولة لا نصّ حرّ: المفتاح الذي لا يقابله مقياسٌ شارةٌ ميتة (7.4) --}}
                    <label class="text-sm font-semibold">{{ setting('admin.gamification.tabs.badges.mqyas_alshrt_alaly', 'مقياس الشرط الآليّ') }}
                        <select name="condition_key" id="badge-ckey"
                                class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">{{ setting('admin.gamification.tabs.badges.bla_mnh_aly_ydwy', 'بلا منح آليّ (يدويّ)') }}</option>
                            @foreach ($data['conditions'] as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold">{{ setting('admin.gamification.tabs.badges.qymth', 'قيمته') }}
                        <input type="number" min="0" name="condition_value" id="badge-cvalue"
                               class="w-full rounded-xl px-3 py-2 text-sm mt-1"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>
                </div>
                <p class="text-xs mb-3" style="color: var(--text-muted)">
                    {{ setting('admin.gamification.tabs.badges.almqayys_dy_hy_almtaha_fala_akhtr_mnha_ashan', 'المقاييس دي هي المتاحة فعلًا — اختَر منها عشان الشارة تُمنَح آليًّا لحظة استحقاقها.') }}
                </p>

                {{-- 7.4: صورة الشارة تُرفَع لا يُكتَب مسارها --}}
                <label class="block text-sm font-semibold mb-1" for="badge-icon-file">{{ setting('admin.gamification.tabs.badges.swra_alshara', 'صورة الشارة') }}</label>
                <input type="file" name="icon" id="badge-icon-file" accept="image/*"
                       class="w-full rounded-xl px-3 py-2 text-sm mb-1"
                       style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <p class="text-xs mb-3" style="color: var(--text-muted)" data-badge-current-icon></p>
                <input type="hidden" name="icon_path" id="badge-icon">

                <input type="hidden" name="is_active" value="1">

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.gamification.tabs.badges.ahfz_alshara', 'احفظ الشارة') }}</button>
            </form>
        </x-modal>
    @endcan
@endpush

@push('scripts')
    @php
        /*
         | نصوص السكربت من الإعدادات (2.13-أ): لا حرفَ عربيّ داخل `<script>`،
         | فالمحروق هناك لا يصل لوحةَ الإدارة ولا الترجمة.
         */
        $jsText = [
            'current_icon' => setting('admin.gamification.tabs.badges.alhalya', 'الحاليّة:'),
        ];
    @endphp

    <script>
        const HC_BADGE_TEXT = @json($jsText);
        document.querySelectorAll('[data-badge-edit]').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById('badge-id').value = btn.dataset.id;
                document.getElementById('badge-key').value = btn.dataset.key;
                document.getElementById('badge-name').value = btn.dataset.name;
                document.getElementById('badge-name-en').value = btn.dataset.nameEn || '';
                document.getElementById('badge-description').value = btn.dataset.description || '';
                document.getElementById('badge-condition').value = btn.dataset.condition;
                document.getElementById('badge-ckey').value = btn.dataset.ckey || '';
                document.getElementById('badge-cvalue').value = btn.dataset.cvalue || '';
                document.getElementById('badge-icon').value = btn.dataset.icon || '';
                // الصورة الحاليّة تُذكَر بالاسم — الرفع اختياريّ ولا يمسح ما سبق
                const note = document.querySelector('[data-badge-current-icon]');
                if (note) note.textContent = btn.dataset.icon ? HC_BADGE_TEXT.current_icon + ' ' + btn.dataset.icon : '';
                const modal = document.getElementById('badge-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        });
    </script>
@endpush
