@once
    @push('scripts')
        @php
            /*
             | نصوص السكربت من الإعدادات (2.13-أ): لا حرفَ عربيّ داخل `<script>`،
             | فالنصّ المحروق هناك لا يراه محرّرُ اللوحة ولا الترجمة.
             */
            $jsText = [
                'saved' => setting('admin.courses.partials.sortable.atzbt_altrtyb', 'اتظبط الترتيب ✓'),
                'failed' => setting('admin.courses.partials.sortable.altrtyb_ma_athfzsh_jrb_tany', 'الترتيب ما اتحفظش — جرّب تاني.'),
            ];
        @endphp

        <script>
            const HC_SORT_TEXT = @json($jsText);
            /* -----------------------------------------------------------------
             | سحب الصفوف للترتيب — JS خام بلا أيّ مكتبة خارجيّة (دليل البناء).
             | وفشل الحفظ يسترجع الترتيب السابق فلا يكذب على المستخدم (24.1).
             ----------------------------------------------------------------- */
            document.querySelectorAll('[data-sortable]').forEach((list) => {
                let dragged = null;
                const before = () => [...list.querySelectorAll('[data-sort-id]')].map((el) => el.dataset.sortId);
                let snapshot = before();

                list.querySelectorAll('[data-sort-id]').forEach((row) => {
                    row.setAttribute('draggable', 'true');

                    row.addEventListener('dragstart', () => {
                        dragged = row;
                        row.style.opacity = '.5';
                    });

                    row.addEventListener('dragend', () => {
                        row.style.opacity = '';
                        save();
                    });

                    row.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        if (!dragged || dragged === row) return;
                        const rect = row.getBoundingClientRect();
                        const after = (e.clientY - rect.top) / rect.height > 0.5;
                        row.parentNode.insertBefore(dragged, after ? row.nextSibling : row);
                    });
                });

                /* الموبايل: أزرار «فوق/تحت» بدل السحب — اللمس لا يحتمل السحب الدقيق (2.15-ج) */
                list.querySelectorAll('[data-sort-up], [data-sort-down]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const row = btn.closest('[data-sort-id]');
                        const sibling = btn.hasAttribute('data-sort-up')
                            ? row.previousElementSibling
                            : row.nextElementSibling;
                        if (!sibling || !sibling.hasAttribute('data-sort-id')) return;
                        btn.hasAttribute('data-sort-up')
                            ? row.parentNode.insertBefore(row, sibling)
                            : row.parentNode.insertBefore(sibling, row);
                        save();
                    });
                });

                function save() {
                    const ids = before();
                    if (ids.join() === snapshot.join()) return;

                    fetch(list.dataset.sortable, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({ ids }),
                    })
                        .then((r) => {
                            if (!r.ok) throw new Error();
                            snapshot = ids;
                            window.hcToast?.(HC_SORT_TEXT.saved);
                        })
                        .catch(() => {
                            /* استرجاع الترتيب السابق عند الفشل */
                            snapshot.forEach((id) => {
                                const el = list.querySelector(`[data-sort-id="${id}"]`);
                                if (el) list.appendChild(el);
                            });
                            window.hcToast?.(HC_SORT_TEXT.failed, 'danger');
                        });
                }
            });
        </script>
    @endpush
@endonce
