@props([])

{{--
  ⭐ البحث الموحّد (Ctrl+K) — 2.15-د:
  حقل واحد يصل إلى أيّ صفحة أو شخص أو مهمّة بالكتابة، بديلًا عن التنقّل في
  السايد بار. وعلى الموبايل: أيقونة البحث في الهيدر تفتح **شاشة بحث كاملة**.
--}}
<div id="command-palette" class="fixed inset-0 z-[60] hidden items-start justify-center p-4 pt-24"
     style="background: rgb(0 0 0 / .55)" data-palette role="dialog" aria-modal="true" aria-label="البحث الموحّد">
    <div class="card w-full max-w-xl overflow-hidden" style="max-height: 70vh">
        <div class="flex items-center gap-2 px-4 py-3" style="border-bottom: 1px solid var(--border)">
            {{-- أيقونة SVG مرسومة داخل المشروع (2.16-ج) --}}
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" aria-hidden="true" style="color: var(--text-muted)">
                <circle cx="11" cy="11" r="7" />
                <path d="M20 20l-3.5-3.5" />
            </svg>
            <input type="search" data-palette-input autocomplete="off"
                   placeholder="اكتب اسم صفحة أو شخص أو مهمّة…" aria-label="البحث الموحّد"
                   class="flex-1 bg-transparent outline-none text-sm" style="color: var(--text); min-height: 44px">
            <kbd class="text-[10px] rounded px-1.5 py-0.5 hidden sm:inline"
                 style="background: var(--surface-sunken); color: var(--text-muted)">Ctrl K</kbd>
            <button type="button" data-palette-close aria-label="إغلاق"
                    class="text-sm opacity-70 hover:opacity-100" style="min-width: 44px; min-height: 44px">✕</button>
        </div>

        <div data-palette-results class="overflow-y-auto p-2 text-sm" style="max-height: calc(70vh - 60px)">
            {{-- الحالة الفارغة سطر واحد يشجّع ولا يعاتب (2.17-ج) --}}
            <p class="p-3 text-xs" style="color: var(--text-muted)">اكتب حرفين وهنوصّلك على طول.</p>
        </div>
    </div>
</div>
