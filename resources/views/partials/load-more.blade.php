{{--
  زرّ التمرير التدريجيّ العامّ (13.1 · قرار §25 — ⛔ ممنوع ترقيم الصفحات).
  نفس نمط `account/search.blade.php` بالحرف: رابطٌ بـ`offset` يعمل بلا JS،
  وفي حضور JS يُجلَب Fragment ويُلحَق ثمّ يتحرّك الـ`offset` — والزرّ يختفي
  لمّا يرجع الردّ فارغًا. مُعمَّم هنا ليخدم أكثر من هدف واحد (`targetSelector`
  يقبل أهدافًا متعدّدة مفصولة بفاصلة لشاشات الجدول/الكروت الموازية كالمحفظة).

  المتغيّرات المتوقَّعة من المستدعي:
  - hasMore (bool) — هل يُعرَض الزرّ أصلًا
  - moreUrl (string) — رابط أوّل جلبة تالية، فلاتر الصفحة الحاليّة محقونة فيه
  - nextOffset (int) — الإزاحة التي سيطلبها هذا الرابط
  - pageSize (int) — خطوة الإزاحة لكلّ جلبة تالية
  - targetSelector (string) — Selector أو أكثر (مفصولة بفاصلة) للحاوية التي يُلحَق بها الفراجمنت
  - label (string اختياريّ) — نصّ الزرّ؛ افتراضيّه إعداد `ux.lists.load_more`
--}}
@if ($hasMore ?? false)
    <div class="text-center mt-4" data-load-more-wrap>
        <a href="{{ $moreUrl }}"
           data-load-more
           data-load-more-target="{{ $targetSelector }}"
           data-load-more-step="{{ $pageSize }}"
           data-next-offset="{{ $nextOffset }}"
           class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm motion-standard"
           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $label ?? setting('ux.lists.load_more', 'عرض المزيد') }}</a>
    </div>
@endif

@once
    @push('scripts')
        <script>
            /* ---------------------------------------------------------------
             | تمرير تدريجيّ عامّ (13.1 · قرار §25): بلا ترقيم صفحات إطلاقًا.
             | يخدم هدفًا واحدًا (شبكة/قائمة) أو هدفَين متوازيَين (جدول سطح
             | المكتب + كروت الموبايل)، والتفريق بينهما بعنصر <template data-for>
             | يحمل كلّ نصف الردّ لمّا كانت الأهداف أكثر من هدف.
             --------------------------------------------------------------- */
            document.querySelectorAll('[data-load-more]').forEach((button) => {
                const targets = (button.dataset.loadMoreTarget || '').split(',').map((s) => s.trim()).filter(Boolean);
                if (!targets.length) return;

                const step = Number(button.dataset.loadMoreStep || 0);
                let loading = false;

                const loadMore = async () => {
                    if (loading) return;
                    loading = true;

                    try {
                        const res = await fetch(button.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        const html = await res.text();
                        let appended = false;

                        if (targets.length > 1) {
                            // ردّ مركّب: كلّ هدف له <template data-for="selector"> خاصّ به
                            const tmp = document.createElement('template');
                            tmp.innerHTML = html;

                            targets.forEach((sel) => {
                                const target = document.querySelector(sel);
                                const block = tmp.content.querySelector(`template[data-for="${sel}"]`);
                                if (target && block && block.innerHTML.trim()) {
                                    target.insertAdjacentHTML('beforeend', block.innerHTML);
                                    appended = true;
                                }
                            });
                        } else {
                            const target = document.querySelector(targets[0]);
                            if (target && html.trim()) {
                                target.insertAdjacentHTML('beforeend', html);
                                appended = true;
                            }
                        }

                        const next = Number(button.dataset.nextOffset) + step;
                        const url = new URL(button.href, window.location.origin);
                        url.searchParams.set('offset', String(next));
                        button.href = url.toString();
                        button.dataset.nextOffset = String(next);

                        // انتهت النتائج: الزرّ يختفي بدل أن يبقى بلا فائدة (2.15-أ-7)
                        if (!appended) {
                            button.closest('[data-load-more-wrap]')?.remove();
                        }
                    } catch {
                        /* الشبكة اتقطعت — الزرّ فاضل مكانه ويقدر يجرّب تاني */
                    } finally {
                        loading = false;
                    }
                };

                button.addEventListener('click', (e) => { e.preventDefault(); loadMore(); });

                if ('IntersectionObserver' in window) {
                    new IntersectionObserver((entries) => {
                        if (entries.some((entry) => entry.isIntersecting)) loadMore();
                    }, { rootMargin: '200px' }).observe(button);
                }
            });
        </script>
    @endpush
@endonce
