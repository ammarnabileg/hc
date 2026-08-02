{{--
  بوب-أب ثابت الظهور (لا يعتمد على الجافاسكربت كي لا تُقفَل الشاشة أبدًا)
  برأس ثابت وجسم متمرّر بحدّ 92vh (2.10.1-17).
--}}
<div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgb(0 0 0 / .55)"
     role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div class="modal-shell card w-full max-w-lg animate-fadeup">
        <div class="modal-head flex items-center justify-between px-5 py-4" style="border-bottom: 1px solid var(--border)">
            <h2 class="font-bold">{{ $title }}</h2>
            @isset($close)
                {{ $close }}
            @endisset
        </div>
        <div class="modal-body px-5 py-4">{{ $slot }}</div>
        @isset($footer)
            <div class="modal-head px-5 py-4" style="border-top: 1px solid var(--border)">{{ $footer }}</div>
        @endisset
    </div>
</div>
