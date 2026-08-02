@canany(['onboarding.create', 'onboarding.edit'])
    @push('modals')
        {{-- التفاصيل في بوب-أب لا صفحة جديدة — فلا يفقد الأدمن مكانه (2.15-أ-6) --}}
        <x-modal id="slide-form" title="شريحة ترحيب">
            <form method="post" action="{{ route('admin.ops.onboarding.slides.store') }}"
                  enctype="multipart/form-data" id="slide-form-el" class="space-y-3">
                @csrf
                <input type="hidden" name="_method" value="post" data-method>
                <input type="hidden" name="screen" value="{{ $screen }}">

                <x-form.input name="title_ar" label="العنوان" hint="سطر واحد يقول الفكرة." required />

                <label class="block">
                    <span class="block text-sm mb-1">النصّ</span>
                    <textarea name="body_ar" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                    <span class="block text-xs mt-1" style="color: var(--text-muted)">سطرين على الأكثر — المستخدم بيقرأ بسرعة.</span>
                </label>

                <div class="grid gap-3 md:grid-cols-2">
                    <x-form.input name="action_label" label="نصّ زرّ الإجراء" hint="سيبه فاضي لو مافيش زرّ." />
                    <x-form.input name="action_url" label="رابط الزرّ" hint="مثال: /learning/courses" />
                </div>

                <label class="block">
                    <span class="block text-sm mb-1">الصورة</span>
                    <input type="file" name="image" accept="image/*" class="text-sm w-full">
                    <span class="block text-xs mt-1" style="color: var(--text-muted)">
                        الحدّ الأقصى {{ (int) setting('onboarding.slides.image_max_kb', 2048) }} ك.ب.
                    </span>
                </label>

                <label class="flex items-center gap-2 text-sm" style="min-height: 44px">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked data-active-input>
                    ظاهرة للمستخدم
                </label>
            </form>

            <x-slot:footer>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs" style="color: var(--text-muted)">التغيير بيبان في المعاينة فورًا بعد الحفظ.</span>
                    <button form="slide-form-el" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">حفظ</button>
                </div>
            </x-slot:footer>
        </x-modal>
    @endpush

    @push('scripts')
        <script>
            // نفس البوب-أب للإضافة والتعديل — نمط واحد لا نمطان (2.15-ب)
            (function () {
                const form = document.getElementById('slide-form-el');
                if (!form) return;

                const storeUrl = @json(route('admin.ops.onboarding.slides.store'));
                const updateBase = @json(url('/admin/ops/onboarding/slides'));

                document.addEventListener('click', (e) => {
                    const add = e.target.closest('[data-slide-new]');
                    if (add) {
                        form.action = storeUrl;
                        form.querySelector('[data-method]').value = 'post';
                        form.reset();
                        const active = form.querySelector('[data-active-input]');
                        if (active) active.checked = true;
                    }

                    const edit = e.target.closest('[data-slide-edit]');
                    if (edit) {
                        form.action = updateBase + '/' + edit.dataset.slideEdit;
                        form.querySelector('[data-method]').value = 'put';
                        form.querySelector('[name="title_ar"]').value = edit.dataset.title || '';
                        form.querySelector('[name="body_ar"]').value = edit.dataset.body || '';
                        form.querySelector('[name="action_label"]').value = edit.dataset.actionLabel || '';
                        form.querySelector('[name="action_url"]').value = edit.dataset.actionUrl || '';
                        const active = form.querySelector('[data-active-input]');
                        if (active) active.checked = edit.dataset.active === '1';
                    }
                });
            })();
        </script>
    @endpush
@endcanany
