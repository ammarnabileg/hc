{{--
    ⭐ **بوب-أب «اختَر من المكتبة / ارفع جديد»** (12.4-د · 12.4-هـ · 24.1).

    الدستور 12.4-هـ حرفيًّا: «**إعادة الاستخدام:** أيّ حقل رفع (غلاف/مرفق/صورة
    سؤال) يفتح **«اختَر من المكتبة»** أو **«ارفع جديد»** — يترفع مرّة ويُعاد
    استخدامه». و24.1 (مكتبة الوسائط): «بوب-أب **«اختَر من المكتبة / ارفع جديد»**
    المستدعى من أيّ حقل رفع».

    الاستعمال: ضع بجوار الحقل زرًّا يحمل `data-media-pick="اسم الحقل"`، وأدرج
    هذا الجزء مرّةً واحدة في الصفحة. ولا مكتبة خارجيّة ولا أيقونة جاهزة (2.1).
--}}

<div id="media-picker-modal" class="fixed inset-0 z-[70] hidden items-start justify-center p-4 pt-16"
     style="background: rgb(0 0 0 / .55)" data-picker-modal role="dialog" aria-modal="true"
     aria-label="{{ setting('media.picker.title') }}">
    <div class="card w-full max-w-3xl overflow-hidden" style="max-height: 82vh">
        <div class="flex items-center justify-between gap-2 px-4 py-3" style="border-bottom: 1px solid var(--border)">
            <strong class="text-sm">{{ setting('media.picker.title') }}</strong>
            <button type="button" data-picker-close aria-label="{{ setting('media.picker.close') }}"
                    class="rounded-lg px-2 py-1 text-sm" style="background: var(--surface-sunken)">✕</button>
        </div>

        @can('media_library.create')
            {{-- «ارفع جديد» داخل البوب-أب نفسه: يترفع ويُختار في خطوة واحدة (12.4-د) --}}
            <div class="px-4 py-3" style="border-bottom: 1px solid var(--border)">
                <label class="block text-sm">
                    <span class="block mb-1">{{ setting('media.picker.upload_label') }}</span>
                    <input type="file" data-picker-upload class="w-full text-sm">
                </label>
                <p class="text-xs mt-1" data-picker-upload-note style="color: var(--text-muted)">
                    {{ setting('media.picker.upload_hint') }}
                </p>
            </div>
        @endcan

        <div class="p-4 overflow-y-auto" style="max-height: 58vh" data-picker-body>
            <p class="text-sm" style="color: var(--text-muted)">{{ setting('media.picker.loading') }}</p>
        </div>
    </div>
</div>

@once
@push('scripts')
    <script>
        /*
         * منطق البوب-أب: يفتح، يجلب شبكة المكتبة كجزءٍ عارٍ (`?fragment=1`)،
         * وعند الاختيار يكتب المسار في الحقل الهدف ويطلق `input` و`change`
         * فيلتقطهما الحفظ التلقائيّ للفورم (12.4-ب) كأنّ المستخدم كتب بيده.
         */
        (function () {
            const modal = document.querySelector('[data-picker-modal]');
            if (!modal) return;

            const body = modal.querySelector('[data-picker-body]');
            const base = @json(route('admin.media.picker'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            let targetName = null;

            function open(name) {
                targetName = name;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                load('');
            }

            function close() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                targetName = null;
            }

            function load(url) {
                const href = url || (base + '?fragment=1&target=' + encodeURIComponent(targetName || ''));
                body.innerHTML = '<p class="text-sm" style="color: var(--text-muted)">' + @json(setting('media.picker.loading')) + '</p>';

                fetch(href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then((r) => r.text())
                    .then((html) => { body.innerHTML = html; })
                    .catch(() => {
                        body.innerHTML = '<p class="text-sm">' + @json(setting('media.picker.load_error')) + '</p>';
                    });
            }

            /** يكتب المسار في الحقل ويعرض معاينته — ثمّ يغلق: خطوة واحدة لا نسخ ولا لصق */
            function apply(path, url, isImage) {
                if (!targetName) return close();

                const field = document.querySelector('[name="' + targetName + '"]');
                if (field) {
                    field.value = path;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                }

                const preview = document.querySelector('[data-media-preview="' + targetName + '"]');
                if (preview) {
                    preview.innerHTML = isImage
                        ? '<img src="' + url + '" alt="" class="w-20 h-20 object-cover rounded-lg">'
                        : '<span class="text-xs">' + path + '</span>';
                }

                close();
            }

            document.addEventListener('click', (e) => {
                const opener = e.target.closest('[data-media-pick]');
                if (opener) {
                    e.preventDefault();
                    open(opener.dataset.mediaPick);
                    return;
                }

                if (e.target.closest('[data-picker-close]') || e.target === modal) {
                    close();
                    return;
                }

                const card = e.target.closest('[data-pick]');
                if (card && modal.contains(card)) {
                    e.preventDefault();
                    apply(card.dataset.pick, card.dataset.pickUrl, card.dataset.pickImage === '1');
                    return;
                }

                // ترقيم الصفحات داخل البوب-أب: يبقى داخله ولا يغادر الفورم
                const page = e.target.closest('[data-picker-pagination] a');
                if (page && modal.contains(page)) {
                    e.preventDefault();
                    load(page.href);
                }
            });

            document.addEventListener('submit', (e) => {
                const form = e.target.closest('[data-picker-search]');
                if (!form || !modal.contains(form)) return;
                e.preventDefault();
                load(form.action + '?' + new URLSearchParams(new FormData(form)).toString());
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
            });

            // «ارفع جديد»: يرفع ثمّ يختار مباشرةً — والخادم يردّ بالنسخة الموجودة لو مكرّرة (12.4-د)
            const upload = modal.querySelector('[data-picker-upload]');
            const note = modal.querySelector('[data-picker-upload-note]');

            upload?.addEventListener('change', () => {
                if (!upload.files?.length) return;

                const data = new FormData();
                data.append('file', upload.files[0]);
                if (note) note.textContent = @json(setting('media.picker.uploading'));

                fetch(@json(route('admin.media.store')), {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then((r) => r.json())
                    .then((json) => {
                        if (note) note.textContent = json.message || '';
                        upload.value = '';
                        if (json.item) apply(json.item.path, json.item.url, true);
                    })
                    .catch(() => { if (note) note.textContent = @json(setting('media.picker.upload_error')); });
            });
        })();
    </script>
@endpush
@endonce
