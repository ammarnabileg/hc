@push('modals')
    <x-modal id="new-item" title="عنصر جديد">
        {{-- التفاصيل في بوب-أب لا صفحة جديدة — والمستخدم لا يفقد مكانه (2.15-أ-6) --}}
        @if ($tab === 'coupons')
            <form method="post" action="{{ route('admin.store.coupons.store') }}" class="space-y-3">
                @csrf
                <x-form.input name="code" label="كود الكوبون" required />
                <label class="block text-sm">
                    <span class="block mb-1">نوع الخصم</span>
                    <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="percent">نسبة %</option>
                        <option value="fixed">قيمة ثابتة</option>
                    </select>
                </label>
                <x-form.input name="value" label="القيمة" type="number" required />
                <x-form.input name="max_uses" label="حدّ الاستخدام الكلّيّ (فاضي = بلا حدّ)" type="number" />
                <x-form.input name="max_uses_per_user" label="حدّ الاستخدام للمستخدم" type="number" value="1" required />
                <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">حفظ الكوبون</button>
            </form>
        @elseif ($tab === 'bundles')
            <form method="post" action="{{ route('admin.store.bundles.store') }}" class="space-y-3">
                @csrf
                <x-form.input name="name_ar" label="اسم البندل" required />
                <x-form.input name="price_coins" label="سعر البندل (كوينز)" type="number" required />
                {{-- ⭐ لا حقل «القيمة الإجماليّة»: تُحسَب من عناصر الباقة (18 · 2.9) --}}
                <p class="text-xs" style="color: var(--text-muted)">
                    القيمة الإجماليّة بتتحسب تلقائيًّا من عناصر الباقة بعد ما تضيفها.
                </p>
                <label class="block text-sm">
                    <span class="block mb-1">الحالة</span>
                    <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="draft">مسودّة</option>
                        <option value="published">منشور</option>
                    </select>
                </label>
                <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">حفظ البندل</button>
            </form>
        @else
            <form method="post" action="{{ route('admin.store.products.store') }}" class="space-y-3">
                @csrf
                <x-form.input name="name_ar" label="اسم المنتج" required />
                <label class="block text-sm">
                    <span class="block mb-1">التصنيف</span>
                    <select name="product_category_id" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="">بلا تصنيف</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name_ar }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="block mb-1">النوع</span>
                    <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="digital">رقميّ</option>
                        <option value="protected_pdf">PDF محميّ (Flip-only)</option>
                        <option value="cv_template">قالب CV</option>
                    </select>
                </label>
                {{-- ⭐ التسعير متعدّد العملات (17): عملةٌ معلَنة وقيمةٌ واحدة لا ثلاثة أعمدة --}}
                <label class="block text-sm">
                    <span class="block mb-1">عملة السعر</span>
                    <select name="price_currency" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        @foreach (app(\App\Services\Store\StoreCatalog::class)->currencyOptions() as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <x-form.input name="price" label="السعر" type="number" step="0.01" required />
                <label class="block text-sm">
                    <span class="block mb-1">الحالة</span>
                    <select name="status" class="w-full rounded-xl px-3 py-2 text-sm"
                            style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                        <option value="draft">مسودّة</option>
                        <option value="published">منشور</option>
                    </select>
                </label>
                <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color: #04201c">حفظ المنتج</button>
            </form>
        @endif
    </x-modal>
@endpush
