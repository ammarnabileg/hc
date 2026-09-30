@props(['message' => '', 'state' => 'ok'])

{{--
  رسالة الخادم بعد الفعل (2.17-ب). النجاح العابر يُعرَض بتوست المنصّة نفسه الذي
  يعرضه السكربت (`#toast` في المرجع): كبسولة أسفل الشاشة تختفي وحدها بعد مدّةٍ
  تتناسب مع طول النصّ (app.js)، وبلا جافاسكربت تبقى ظاهرة. أمّا التحذير والخطأ
  فيبقيان بطاقةً ثابتة في مكانها فوق النموذج حتى يقرأها المستخدم ويتصرّف.
--}}
@if ((string) $message !== '')
    @if ($state === 'ok')
        <div class="toast show" role="status" data-flash-toast>{{ $message }}</div>
    @else
        <div class="card p-3 mb-4 flex items-center gap-2 animate-fadeup" role="{{ $state === 'danger' ? 'alert' : 'status' }}">
            <x-state-badge :state="$state" label="" />
            <span class="text-sm">{{ $message }}</span>
        </div>
    @endif
@endif
