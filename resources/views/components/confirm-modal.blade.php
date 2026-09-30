@props([])

{{--
  بوب-أب التأكيد المشترك — بديلٌ عن `confirm()` الخام للمتصفّح على الأفعال الحسّاسة
  (حذف · إرجاع للافتراضيّ · تدوير مفتاح · قفل الجلسات). أيّ نموذج أو زرّ يحمل
  `data-confirm="النصّ"` يمرّ من هنا (resources/js/app.js)، فتُعرَض الرسالة بخطّ
  المنصّة ومظهرها الفاتح/الداكن، وبزرّين واضحين، وعلى الموبايل كـBottom Sheet
  (2.10.1-17). ونصوص الأزرار من `setting()` (2.13).
--}}
<div id="confirm-modal" class="fixed inset-0 z-[70] hidden items-center max-md:items-end justify-center p-4 max-md:p-0"
     style="background: rgb(0 0 0 / .55)" data-modal data-confirm-modal role="alertdialog" aria-modal="true"
     aria-labelledby="confirm-modal-title" aria-describedby="confirm-modal-message">
    <div class="modal-shell card w-full max-w-md max-md:max-w-full max-md:rounded-b-none max-md:relative">
        <div class="modal-head flex items-center gap-3 px-5 py-4 max-md:pt-6">
            <span class="hidden max-md:block absolute rounded-full" aria-hidden="true"
                  style="top: 9px; inset-inline-start: calc(50% - 18px); width: 36px; height: 4px; background: var(--border)"></span>
            <span class="flex items-center justify-center shrink-0" aria-hidden="true"
                  style="inline-size: 40px; block-size: 40px; border-radius: 9999px; background: var(--brand-soft, var(--surface-sunken)); color: var(--color-brand-500)">
                <x-icon name="warning" size="20" />
            </span>
            <h2 id="confirm-modal-title" class="font-bold">{{ setting('ux.confirm.title', 'متأكّد؟') }}</h2>
        </div>
        <div class="modal-body px-5 pb-4">
            <p id="confirm-modal-message" data-confirm-message class="text-sm leading-relaxed" style="color: var(--text-muted)"></p>
        </div>
        <div class="flex flex-wrap justify-end gap-2 px-5 py-4 max-md:pb-[calc(16px+env(safe-area-inset-bottom))]" style="border-top: 1px solid var(--border)">
            <button type="button" class="btn rounded-xl px-4 text-sm" style="min-height: 44px; background: var(--surface-sunken)" data-modal-close>
                {{ setting('ux.confirm.cancel', 'رجوع') }}
            </button>
            <button type="button" class="btn btn-p rounded-xl px-4 text-sm font-semibold" style="min-height: 44px" data-confirm-ok>
                {{ setting('ux.confirm.ok', 'نعم، كمّل') }}
            </button>
        </div>
    </div>
</div>
