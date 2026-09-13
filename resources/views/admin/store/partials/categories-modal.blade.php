@php
    /*
     | ⭐ مودال إدارة التصنيفات (24.3-أوّلًا «المنتجات والتصنيفات»): إضافة ·
     | تعديل · أرشفة (تخفي دون فقد ارتباط المنتجات) · حذف نهائيّ بتأكيد ·
     | وسحبٌ لإعادة الترتيب — نفس نمط فورم «تعديل/جديد» الواحد المستعمَل في
     | resources/views/admin/courses/paths.blade.php.
     */
    $categoryPayload = fn ($category) => json_encode([
        'url' => route('admin.store.categories.update', $category),
        'name_ar' => $category->name_ar,
        'name_en' => $category->name_en,
    ]);
@endphp

@push('modals')
    <x-modal id="categories" :title="setting('admin.store.partials.categories_modal.altsnyfat', 'التصنيفات')">
        @canany(['product_categories.create', 'product_categories.edit'])
            <div class="card p-3 mb-5">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h3 class="font-bold text-sm" data-category-form-heading>{{ setting('admin.store.partials.categories_modal.idafa_tsnyf_jdyd', 'إضافة تصنيف جديد') }}</h3>
                    <button type="button" class="text-xs underline hidden" data-category-reset
                            style="color: var(--text-muted)">{{ setting('admin.store.partials.categories_modal.alghaa_altadyl', 'إلغاء — تصنيف جديد') }}</button>
                </div>

                <form method="post" action="{{ route('admin.store.categories.store') }}" data-category-form class="space-y-3">
                    @csrf
                    <input type="hidden" name="_method" value="post" data-category-method>
                    <div class="grid md:grid-cols-2 gap-3">
                        <x-form.input name="name_ar" :label="setting('admin.store.partials.categories_modal.alasm_arby', 'الاسم (عربيّ)')" required />
                        <x-form.input name="name_en" :label="setting('admin.store.partials.categories_modal.alasm_injlyzy', 'الاسم (إنجليزيّ)')" />
                    </div>
                    <button class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.store.partials.categories_modal.hfz_altsnyf', 'حفظ التصنيف') }}</button>
                </form>
            </div>
        @endcanany

        @if ($categories->isEmpty())
            <p class="text-sm text-center py-6" style="color: var(--text-muted)">{{ setting('admin.store.partials.categories_modal.mafysh_tsnyfat_lsh', 'مفيش تصنيفات لسّه — ابدأ بإضافة واحد فوق.') }}</p>
        @else
            {{-- شجرة/قائمة قابلة للسحب لإعادة الترتيب (24.3) — نفس منطق sortable.blade.php العامّ --}}
            <div class="card overflow-hidden" data-sortable="{{ route('admin.store.categories.reorder') }}">
                @foreach ($categories as $category)
                    <div class="p-3 flex items-center gap-3" style="border-top: 1px solid var(--border)" data-sort-id="{{ $category->id }}">
                        <span class="cursor-grab select-none shrink-0" aria-label="{{ setting('admin.store.partials.categories_modal.mqbd_alsahb', 'مقبض السحب') }}">⠿</span>

                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-sm truncate">{{ $category->name_ar }}</div>
                            <div class="text-xs" style="color: var(--text-muted)">
                                {{ $category->products_count }} {{ setting('admin.store.partials.categories_modal.mntj', 'منتج') }}
                            </div>
                        </div>

                        <x-state-badge :state="$category->is_active ? 'ok' : 'idle'"
                                       :label="$category->is_active ? setting('admin.store.partials.categories_modal.nasht', 'نشط') : setting('admin.store.partials.categories_modal.mkhfy', 'مخفي')" />

                        <details class="relative shrink-0">
                            <summary class="cursor-pointer list-none rounded-xl text-sm flex items-center justify-center"
                                     style="background: var(--surface-raised); min-width: 44px; min-height: 44px"
                                     aria-label="{{ setting('admin.store.partials.categories_modal.ijraat', 'إجراءات') }}">⋯</summary>
                            <div class="card absolute end-0 mt-1 p-2 w-48 z-20 text-sm space-y-1">
                                @can('product_categories.edit')
                                    <button type="button" class="block w-full text-start px-2 py-1 rounded-lg"
                                            data-category-edit="{{ $categoryPayload($category) }}">
                                        <x-icon name="edit" size="16" /> {{ setting('admin.store.partials.categories_modal.tadyl', 'تعديل') }}
                                    </button>
                                @endcan
                                @can('product_categories.archive')
                                    <form method="post" action="{{ route('admin.store.categories.archive', $category) }}">
                                        @csrf
                                        <button type="submit" class="block w-full text-start px-2 py-1 rounded-lg">
                                            {{ $category->is_active
                                                ? setting('admin.store.partials.categories_modal.ikhfaa', 'إخفاء')
                                                : setting('admin.store.partials.categories_modal.izhar', 'إظهار') }}
                                        </button>
                                    </form>
                                @endcan
                                @can('product_categories.delete')
                                    <form method="post" action="{{ route('admin.store.categories.destroy', $category) }}"
                                          onsubmit="return confirm('{{ $category->products_count > 0
                                              ? strtr((string) setting('admin.store.partials.categories_modal.confirm_hdhf_ma_mntjat', 'هنحذف التصنيف؛ :a1 منتج هيبقى بلا تصنيف. نكمّل؟'), [':a1' => (string) $category->products_count])
                                              : setting('admin.store.partials.categories_modal.confirm_hdhf_fady', 'هنحذف التصنيف؟ مفيش منتجات فيه — مش هيرجع تاني.') }}')">
                                        @csrf @method('delete')
                                        <button type="submit" class="block w-full text-start px-2 py-1 rounded-lg" style="color: var(--color-state-danger)">
                                            <x-icon name="trash" size="16" /> {{ setting('admin.store.partials.categories_modal.hdhf', 'حذف') }}
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </details>
                    </div>
                @endforeach
            </div>
        @endif
    </x-modal>
@endpush

@include('admin.courses.partials.sortable')

@once
    @push('scripts')
        @php
            $categoryFormText = [
                'add' => setting('admin.store.partials.categories_modal.idafa_tsnyf_jdyd', 'إضافة تصنيف جديد'),
                'edit' => setting('admin.store.partials.categories_modal.tadyl_altsnyf', 'تعديل تصنيف'),
            ];
        @endphp
        <script>
            /* فورم واحد يتبدّل بين إضافة وتعديل تصنيف — بلا مودال ثانٍ (نمط paths.blade.php) */
            (function () {
                const HC_CATEGORY_TEXT = @json($categoryFormText);
                const form = document.querySelector('[data-category-form]');
                if (!form) return;

                const storeUrl = form.action;
                const heading = document.querySelector('[data-category-form-heading]');
                const resetBtn = document.querySelector('[data-category-reset]');

                const fill = (data) => {
                    form.action = data?.url || storeUrl;
                    form.querySelector('[data-category-method]').value = data ? 'put' : 'post';
                    ['name_ar', 'name_en'].forEach((key) => {
                        const field = form.querySelector(`[name="${key}"]`);
                        if (field) field.value = data?.[key] ?? '';
                    });
                    if (heading) heading.textContent = data ? HC_CATEGORY_TEXT.edit : HC_CATEGORY_TEXT.add;
                    if (resetBtn) resetBtn.classList.toggle('hidden', !data);
                };

                document.addEventListener('click', (e) => {
                    const editBtn = e.target.closest('[data-category-edit]');
                    if (editBtn) fill(JSON.parse(editBtn.dataset.categoryEdit));

                    if (e.target.closest('[data-category-reset]')) fill(null);
                });
            })();
        </script>
    @endpush
@endonce
