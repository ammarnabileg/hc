@props(['id' => 'modal', 'title' => ''])

{{--
  بوب-أب برأس ثابت وجسم متمرّر بحدّ أقصى 92vh (2.10.1-17). وعلى الموبايل
  (2.10.1-17، الهويّة 2.0): **Bottom Sheet** يلتصق بأسفل الشاشة براديوس علويّ
  فقط، ومقبض سحب أعلى الرأس — لا صندوقٌ وسطيّ كالديسكتوب.
--}}
<div id="{{ $id }}" class="fixed inset-0 z-50 hidden items-center max-md:items-end justify-center p-4 max-md:p-0"
     style="background: rgb(0 0 0 / .55)" data-modal role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div class="modal-shell card w-full max-w-2xl max-md:max-w-full max-md:rounded-b-none max-md:relative">
        <div class="modal-head flex items-center justify-between px-5 py-4 max-md:pt-6" style="border-bottom: 1px solid var(--border)">
            {{-- مقبض السحب — تلميحٌ بصريّ فقط (اللمس الفعليّ في data-modal-sheet بـ resources/js/app.js) --}}
            <span class="hidden max-md:block absolute rounded-full" aria-hidden="true"
                  style="top: 9px; inset-inline-start: calc(50% - 18px); width: 36px; height: 4px; background: var(--border)"></span>
            <h2 class="font-bold">{{ $title }}</h2>
            <button type="button" class="text-sm opacity-70 hover:opacity-100" data-modal-close aria-label="{{ setting('ux.modal.aria_label_1', 'إغلاق') }}">✕</button>
        </div>
        <div class="modal-body px-5 py-4 max-md:pb-[calc(20px+env(safe-area-inset-bottom))]">{{ $slot }}</div>
        @isset($footer)
            <div class="modal-head px-5 py-4" style="border-top: 1px solid var(--border)">{{ $footer }}</div>
        @endisset
    </div>
</div>
