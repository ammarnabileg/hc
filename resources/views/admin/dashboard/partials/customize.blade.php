@php
    /*
     | بوب-أب «تخصيص اللوحة» (12.3-3 · 24.1): ترتيب الكروت بالسحب وإخفاء ما لا
     | يهمّ **ولكلّ دور ترتيبه**. والتخصيص يعيش في جدول الإعدادات الواحد (2.13)
     | فلا عمود جديد ولا مفتاح مبعثر.
     |
     | ⭐ والمخفيّ هنا **قرار الأدمن** فيُحذَف من اللوحة؛ أمّا الزائد عن الأربعة
     | فيُنقَل لتاب «تفاصيل» ولا يُحذَف (2.15-أ-3).
     */
    $ordered = [];

    foreach ($layout['order'] as $key) {
        if (isset($cardCatalog[$key])) {
            $ordered[$key] = $cardCatalog[$key];
        }
    }

    foreach ($cardCatalog as $key => $label) {
        $ordered[$key] ??= $label;
    }
@endphp

<x-modal id="dashboard-layout" title="تخصيص اللوحة">
    <form method="post" action="{{ route('admin.dashboard.layout') }}">
        @csrf
        <input type="hidden" name="role" value="{{ $roleKey }}">

        <p class="text-sm mb-3" style="color: var(--text-muted)">
            {{ setting('admin.dashboard.customize_hint', 'رتّب الكروت بالسحب، وشيل اللي مش محتاجه — والترتيب ده بيتحفظ لدورك أنت.') }}
            <span class="block mt-1">الدور الحاليّ: <b>{{ $roleKey }}</b></span>
        </p>

        <ul data-layout-list class="space-y-2">
            @foreach ($ordered as $key => $label)
                <li data-layout-row="{{ $key }}"
                    class="card p-3 flex flex-wrap items-center gap-3" style="background: var(--surface-sunken)">
                    <span aria-hidden="true" style="color: var(--text-muted); cursor: grab">⋮⋮</span>
                    <input type="hidden" name="order[]" value="{{ $key }}">

                    <span class="min-w-0 flex-1 truncate text-sm">{{ $label }}</span>

                    {{-- أزرار «فوق/تحت» للموبايل — اللمس لا يحتمل السحب الدقيق (2.15-ج) --}}
                    <button type="button" data-layout-up aria-label="حرّك لفوق"
                            class="rounded-lg px-2 text-sm" style="min-width: 44px; min-height: 44px; background: var(--surface-raised)">▲</button>
                    <button type="button" data-layout-down aria-label="حرّك لتحت"
                            class="rounded-lg px-2 text-sm" style="min-width: 44px; min-height: 44px; background: var(--surface-raised)">▼</button>

                    <label class="flex items-center gap-2 text-xs cursor-pointer" style="min-height: 44px">
                        <input type="checkbox" name="hidden[]" value="{{ $key }}"
                               @checked(in_array($key, $layout['hidden'], true))>
                        <span>إخفاء</span>
                    </label>
                </li>
            @endforeach
        </ul>

        <div class="mt-4 flex items-center gap-2">
            <button type="submit" class="btn rounded-xl px-4 py-3 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c; min-height: 44px">احفظ للدور ده</button>
            <button type="button" data-modal-close class="rounded-xl px-4 py-3 text-sm"
                    style="background: var(--surface-sunken); color: var(--text); min-height: 44px">إلغاء</button>
        </div>
    </form>
</x-modal>

@push('scripts')
    <script>
        /* سحب الكروت للترتيب — JS خام بلا أيّ مكتبة خارجيّة (دليل البناء §4) */
        (function () {
            const list = document.querySelector('[data-layout-list]');
            if (!list) return;

            let dragged = null;

            list.querySelectorAll('[data-layout-row]').forEach((row) => {
                row.setAttribute('draggable', 'true');

                row.addEventListener('dragstart', () => { dragged = row; row.style.opacity = '.5'; });
                row.addEventListener('dragend', () => { row.style.opacity = ''; dragged = null; });

                row.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    if (!dragged || dragged === row) return;
                    const rect = row.getBoundingClientRect();
                    const after = (e.clientY - rect.top) / rect.height > 0.5;
                    row.parentNode.insertBefore(dragged, after ? row.nextSibling : row);
                });
            });

            list.addEventListener('click', (e) => {
                const up = e.target.closest('[data-layout-up]');
                const down = e.target.closest('[data-layout-down]');
                if (!up && !down) return;

                const row = (up || down).closest('[data-layout-row]');
                const sibling = up ? row.previousElementSibling : row.nextElementSibling;
                if (!sibling) return;

                up ? row.parentNode.insertBefore(row, sibling) : row.parentNode.insertBefore(sibling, row);
            });
        })();
    </script>
@endpush
