@props(['id' => 'modal', 'title' => ''])

{{-- بوب-أب برأس ثابت وجسم متمرّر بحدّ أقصى 92vh (2.10.1-17) --}}
<div id="{{ $id }}" class="fixed inset-0 z-50 hidden items-center justify-center p-4"
     style="background: rgb(0 0 0 / .55)" data-modal role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div class="modal-shell card w-full max-w-2xl">
        <div class="modal-head flex items-center justify-between px-5 py-4" style="border-bottom: 1px solid var(--border)">
            <h2 class="font-bold">{{ $title }}</h2>
            <button type="button" class="text-sm opacity-70 hover:opacity-100" data-modal-close aria-label="إغلاق">✕</button>
        </div>
        <div class="modal-body px-5 py-4">{{ $slot }}</div>
        @isset($footer)
            <div class="modal-head px-5 py-4" style="border-top: 1px solid var(--border)">{{ $footer }}</div>
        @endisset
    </div>
</div>
