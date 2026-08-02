{{--
  سياسة الاسترجاع (19.4): Textarea يقبل HTML أو نصًّا عاديًّا · نسختان (ع/إ) ·
  معاينة قبل الحفظ · Audit لكلّ تعديل · Toggles أماكن الظهور.
  ⭐ والمورد `refunds` هنا = عرض وتحرير النصّ فقط — بلا طلبات ولا اعتماد ولا رفض.
--}}
<div class="card p-4">
    <p class="text-sm mb-1"><strong>لا استرجاع نقديّ لأيّ مدفوعات</strong> — والرصيد يبقى في محفظة صاحبه يشتري به من الموقع.</p>
    <p class="text-xs" style="color: var(--text-muted)">
        الاستثناء الوحيد: تصحيح خطأ تقنيّ بمعاملة موثّقة بمرجعها — وليس استردادًا نقديًّا.
    </p>
</div>

@foreach (['ar' => 'النسخة العربيّة', 'en' => 'النسخة الإنجليزيّة'] as $locale => $title)
    <form method="post" action="{{ route('admin.finance.refund-policy') }}" class="card p-4 space-y-3" data-policy-form>
        @csrf
        <input type="hidden" name="locale" value="{{ $locale }}">

        <div class="flex items-center justify-between">
            <h2 class="font-bold text-sm">{{ $title }}</h2>
            <button type="button" class="text-xs underline" data-policy-preview>معاينة</button>
        </div>

        <textarea name="body" rows="8" data-policy-body
                  class="w-full rounded-xl px-3 py-2 text-sm font-mono"
                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                  placeholder="اكتب نصًّا عاديًّا أو HTML…">{{ old('body', $locale === 'ar' ? $policyAr : $policyEn) }}</textarea>

        {{-- المعاينة تُعرَض في صندوق معزول لأنّ النصّ قد يحمل HTML كاملًا --}}
        <div class="rounded-xl p-3 text-sm hidden" data-policy-output
             style="background: var(--surface-sunken); border: 1px dashed var(--border)"></div>

        <label class="block text-sm">
            <span class="block mb-1">سبب التعديل (إلزاميّ — يدخل الـAudit)</span>
            <input type="text" name="reason" required minlength="3"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">حفظ النصّ</button>
    </form>
@endforeach

<div class="card p-4 space-y-3">
    <h2 class="font-bold text-sm">أماكن ظهور السياسة</h2>
    @foreach ($placements as $key => $label)
        <label class="flex items-center justify-between gap-3 text-sm">
            <span>{{ $label }}</span>
            <input type="checkbox" data-toggle-setting="{{ $key }}" @checked(setting($key, true))>
        </label>
    @endforeach
    <p class="text-xs" style="color: var(--text-muted)">فلا يُفاجَأ أحد بعد الدفع.</p>
</div>

@push('scripts')
<script>
// معاينة قبل الحفظ — بلا مكتبة خارجيّة، ونعرض النصّ كما هو داخل صندوق معزول
document.querySelectorAll('[data-policy-form]').forEach(function (form) {
    var button = form.querySelector('[data-policy-preview]');
    var body = form.querySelector('[data-policy-body]');
    var output = form.querySelector('[data-policy-output]');

    button && button.addEventListener('click', function () {
        output.innerHTML = body.value;
        output.classList.toggle('hidden');
    });
});
</script>
@endpush
