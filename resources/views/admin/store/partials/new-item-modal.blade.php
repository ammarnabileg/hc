@push('modals')
    <x-modal id="new-item" :title="$tab === 'library' ? setting('admin.store.partials.new_item_modal.alml_almhmy', 'ملفّ محميّ جديد') : setting('admin.store.partials.new_item_modal.ansr_jdyd', 'عنصر جديد')">
        {{-- التفاصيل في بوب-أب لا صفحة جديدة — والمستخدم لا يفقد مكانه (2.15-أ-6) --}}
        @if ($tab === 'coupons')
            <form method="post" action="{{ route('admin.store.coupons.store') }}" class="space-y-3">
                @csrf
                <x-form.input name="code" :label="setting('admin.store.partials.new_item_modal.kwd_alkwbwn', 'كود الكوبون')" required />
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.store.partials.new_item_modal.nwa_alkhsm', 'نوع الخصم') }}</span>
                    <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="percent">{{ setting('admin.store.partials.new_item_modal.nsba', 'نسبة %') }}</option>
                        <option value="fixed">{{ setting('admin.store.partials.new_item_modal.qyma_thabta', 'قيمة ثابتة') }}</option>
                    </select>
                </label>
                <x-form.input name="value" :label="setting('admin.store.partials.new_item_modal.alqyma', 'القيمة')" type="number" required />
                <x-form.input name="max_uses" :label="setting('admin.store.partials.new_item_modal.hd_alastkhdam_alkly_fady_bla_hd', 'حدّ الاستخدام الكلّيّ (فاضي = بلا حدّ)')" type="number" />
                <x-form.input name="max_uses_per_user" :label="setting('admin.store.partials.new_item_modal.hd_alastkhdam_llmstkhdm', 'حدّ الاستخدام للمستخدم')" type="number" value="1" required />
                <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.store.partials.new_item_modal.hfz_alkwbwn', 'حفظ الكوبون') }}</button>
            </form>
        @elseif ($tab === 'library')
            {{--
                منتج المكتبة الرقميّة (24.3): [الملفّ] الرفع + النوع + الغلاف + الوصف.
                والحماية (Flip-only/العلامة المائيّة/الصلاحيّة الزمنيّة/صفحات العيّنة)
                خطوة تالية من صفّ الجدول («تعديل») بعد ما المنتج يتولد — نفس الترتيب
                القائم أصلًا بين الإنشاء وضبط الحماية.
            --}}
            <form method="post" action="{{ route('admin.store.products.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <x-form.input name="name_ar" :label="setting('admin.store.partials.new_item_modal.asm_alml', 'اسم الملفّ')" required />
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.store.partials.new_item_modal.altsnyf', 'التصنيف') }}</span>
                    <select name="product_category_id" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="">{{ setting('admin.store.partials.new_item_modal.bla_tsnyf', 'بلا تصنيف') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name_ar }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.store.partials.new_item_modal.alnwa', 'النوع') }}</span>
                    <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="digital">{{ setting('admin.store.partials.new_item_modal.rqmy', 'رقميّ') }}</option>
                        <option value="protected_pdf">{{ setting('admin.store.partials.new_item_modal.pdf_mhmy_flip_only', 'PDF محميّ (Flip-only)') }}</option>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.store.partials.new_item_modal.alml', 'الملفّ') }}</span>
                    <input type="file" name="file" class="w-full text-sm">
                    <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ setting('admin.store.partials.new_item_modal.pdf_mp3_wav_mp4_html', 'PDF · MP3 · WAV · MP4 · HTML') }}</span>
                </label>
                {{-- ⭐ «أيّ حقل رفع يفتح اختَر من المكتبة أو ارفع جديد» (12.4-هـ) --}}
                <div>
                    <x-form.input name="cover_path" :label="setting('admin.store.partials.new_item_modal.alghlaf', 'الغلاف')" />
                    <div class="flex items-center gap-2 mt-2">
                        <button type="button" data-media-pick="cover_path"
                                class="rounded-xl px-3 py-1.5 text-xs"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <x-icon name="library" size="14" /> {{ setting('media.picker.cta') }}
                        </button>
                        <span data-media-preview="cover_path" class="inline-flex items-center"></span>
                    </div>
                </div>
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.store.partials.new_item_modal.alwsf', 'الوصف') }}</span>
                    <textarea name="description" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.store.partials.new_item_modal.amla_alsar', 'عملة السعر') }}</span>
                    <select name="price_currency" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        @foreach (app(\App\Services\Store\StoreCatalog::class)->currencyOptions() as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <x-form.input name="price" :label="setting('admin.store.partials.new_item_modal.alsar', 'السعر')" type="number" step="0.01" required />
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.store.partials.new_item_modal.alhala', 'الحالة') }}</span>
                    <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="draft">{{ setting('admin.store.partials.new_item_modal.mswda', 'مسودّة') }}</option>
                        <option value="published">{{ setting('admin.store.partials.new_item_modal.mnshwr', 'منشور') }}</option>
                    </select>
                </label>
                <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.store.partials.new_item_modal.hfz_alml_almhmy', 'حفظ الملفّ المحميّ') }}</button>
            </form>
        @elseif ($tab === 'bundles')
            <form method="post" action="{{ route('admin.store.bundles.store') }}" class="space-y-3">
                @csrf
                <x-form.input name="name_ar" :label="setting('admin.store.partials.new_item_modal.asm_albndl', 'اسم البندل')" required />
                <x-form.input name="price_coins" :label="setting('admin.store.partials.new_item_modal.sar_albndl_kwynz', 'سعر البندل (كوينز)')" type="number" required />
                {{-- ⭐ لا حقل «القيمة الإجماليّة»: تُحسَب من عناصر الباقة (18 · 2.9) --}}
                <p class="text-xs" style="color: var(--text-muted)">
                    {{ setting('admin.store.partials.new_item_modal.alqyma_alijmalya_btthsb_tlqayya_mn_anasr', 'القيمة الإجماليّة بتتحسب تلقائيًّا من عناصر الباقة بعد ما تضيفها.') }}
                </p>
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.store.partials.new_item_modal.alhala', 'الحالة') }}</span>
                    <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="draft">{{ setting('admin.store.partials.new_item_modal.mswda', 'مسودّة') }}</option>
                        <option value="published">{{ setting('admin.store.partials.new_item_modal.mnshwr', 'منشور') }}</option>
                    </select>
                </label>
                <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.store.partials.new_item_modal.hfz_albndl', 'حفظ البندل') }}</button>
            </form>
        @else
            <form method="post" action="{{ route('admin.store.products.store') }}" class="space-y-3">
                @csrf
                <x-form.input name="name_ar" :label="setting('admin.store.partials.new_item_modal.asm_almntj', 'اسم المنتج')" required />
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.store.partials.new_item_modal.altsnyf', 'التصنيف') }}</span>
                    <select name="product_category_id" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="">{{ setting('admin.store.partials.new_item_modal.bla_tsnyf', 'بلا تصنيف') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name_ar }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.store.partials.new_item_modal.alnwa', 'النوع') }}</span>
                    <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="digital">{{ setting('admin.store.partials.new_item_modal.rqmy', 'رقميّ') }}</option>
                        <option value="protected_pdf">{{ setting('admin.store.partials.new_item_modal.pdf_mhmy_flip_only', 'PDF محميّ (Flip-only)') }}</option>
                        <option value="cv_template">{{ setting('admin.store.partials.new_item_modal.qalb_cv', 'قالب CV') }}</option>
                    </select>
                </label>
                {{-- ⭐ التسعير متعدّد العملات (17): عملةٌ معلَنة وقيمةٌ واحدة لا ثلاثة أعمدة --}}
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.store.partials.new_item_modal.amla_alsar', 'عملة السعر') }}</span>
                    <select name="price_currency" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        @foreach (app(\App\Services\Store\StoreCatalog::class)->currencyOptions() as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <x-form.input name="price" :label="setting('admin.store.partials.new_item_modal.alsar', 'السعر')" type="number" step="0.01" required />
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('admin.store.partials.new_item_modal.alhala', 'الحالة') }}</span>
                    <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="draft">{{ setting('admin.store.partials.new_item_modal.mswda', 'مسودّة') }}</option>
                        <option value="published">{{ setting('admin.store.partials.new_item_modal.mnshwr', 'منشور') }}</option>
                    </select>
                </label>
                <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.store.partials.new_item_modal.hfz_almntj', 'حفظ المنتج') }}</button>
            </form>
        @endif
    </x-modal>
@endpush
