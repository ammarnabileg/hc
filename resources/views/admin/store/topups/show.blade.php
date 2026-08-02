@extends('layouts.app')

@section('title', 'مراجعة طلب شحن ' . $request->number)

@section('content')
    <x-page-header :title="'مراجعة الطلب ' . $request->number"
                   subtitle="راجع الإيصال بعينك — الاعتماد مقفول لحدّ ما تراجعه."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'طلبات الشحن', 'url' => route('admin.topups.index')],
                       ['label' => $request->number],
                   ]" />

    @if ($errors->any())
        <div class="card p-3 mb-4 text-sm" style="border: 1px solid var(--color-state-danger); color: var(--color-state-danger)">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($duplicateOf)
        {{-- ⭐ الإيصال المكرَّر (نفس البصمة) يُوسَم «مكرَّرة» وينبّه — ولا يُعتمَد (19.5-ب-5) --}}
        <div class="card p-3 mb-4 text-sm" style="border: 1px solid var(--color-state-warn); color: var(--color-state-warn)">
            ▲ الإيصال ده مرفوع قبل كده في الطلب <strong>{{ $duplicateOf->number }}</strong> — الطلب هيتوسم «مكرَّرة» ومش هيتعتمد.
        </div>
    @endif

    {{-- ⭐ معاينة الإيصال **بجوار الفورم** — عمودان على الديسكتوب وشاشة واحدة على الموبايل --}}
    <div class="grid gap-4 lg:grid-cols-2">

        <div class="space-y-4">
            <div class="card p-4 space-y-2 text-sm">
                <h2 class="font-bold">بيانات الطلب</h2>
                <div class="flex justify-between"><span style="color: var(--text-muted)">القيمة المحوَّلة</span><strong>{{ rtrim(rtrim(number_format((float) $request->transferred_amount, 2), '0'), '.') }}</strong></div>
                <div class="flex justify-between"><span style="color: var(--text-muted)">العرض المختار</span><span>{{ $request->topup_offer?->label_ar ?? 'مبلغ آخر' }}</span></div>
                <div class="flex justify-between"><span style="color: var(--text-muted)">وقت الدفع</span><span>{{ $request->paid_at?->format('Y/m/d H:i') }}</span></div>
                <div class="flex justify-between"><span style="color: var(--text-muted)">طريقة التحويل</span><span>{{ $request->transfer_method?->name_ar ?? '—' }}</span></div>
                <div class="flex justify-between"><span style="color: var(--text-muted)">رقم التواصل</span><span dir="ltr">{{ $request->contact_phone }}</span></div>
                <div class="flex justify-between"><span style="color: var(--text-muted)">بصمة الإيصال</span><code class="text-xs">{{ \Illuminate\Support\Str::limit($request->receipt_hash, 16, '') }}</code></div>
            </div>

            <div class="card p-4 space-y-2 text-sm">
                <h2 class="font-bold">المستخدم ورصيده</h2>
                <div class="flex items-center gap-3">
                    <x-avatar :user="$request->user" size="10" />
                    <div>
                        <div class="font-semibold">{{ $request->user->name }}</div>
                        <div class="text-xs" style="color: var(--text-muted)">#{{ $request->user->code }}</div>
                    </div>
                </div>
                <div class="flex justify-between"><span style="color: var(--text-muted)">الرصيد الحاليّ</span><strong id="balance-before">{{ rtrim(rtrim(number_format($balance, 2), '0'), '.') }}</strong></div>
                @if ($history->isNotEmpty())
                    <div class="text-xs mt-2" style="color: var(--text-muted)">طلباته السابقة: {{ $history->count() }}</div>
                @endif
            </div>

            {{-- ⛔ داخليّ للأدمن فقط — لا يُعرَض للمُرسِل ولا يُعلَن كمهلة (19.5-أ) --}}
            <div class="card p-3 text-xs" style="color: {{ $internalIsLate ? 'var(--color-state-danger)' : 'var(--text-muted)' }}">
                مؤشّر داخليّ: عمر الطلب {{ $internalAgeHours }} ساعة{{ $internalIsLate ? ' — متأخّر' : '' }}. ده للأدمن وحده ومش معلَن للمُرسِل.
            </div>
        </div>

        <div class="space-y-4">
            <div class="card p-4">
                <h2 class="font-bold text-sm mb-2">صورة الإيصال</h2>
                @php $receiptUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($request->receipt_path); @endphp

                @if (\Illuminate\Support\Str::endsWith(strtolower($request->receipt_path), '.pdf'))
                    <a href="{{ $receiptUrl }}" target="_blank" rel="noopener" class="text-sm underline">افتح ملفّ الإيصال (PDF)</a>
                @else
                    <img src="{{ $receiptUrl }}" alt="إيصال التحويل" class="w-full rounded-xl" style="max-width:100%">
                @endif

                @if ($request->status === 'pending_review')
                    @if (! $receiptReviewed)
                        {{-- ⛔ لا موافقة سريعة بضغطة: البوّابة الإلزاميّة قبل أيّ اعتماد --}}
                        <form method="post" action="{{ route('admin.topups.reviewed', $request) }}" class="mt-3">
                            @csrf
                            <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                                    style="background: var(--color-brand-500); color: #04201c">
                                راجعت صورة الإيصال ✓
                            </button>
                            <p class="text-xs mt-2" style="color: var(--text-muted)">
                                مافيش اعتماد قبل المراجعة — حتى لو القيمة مطابقة للعرض بالظبط.
                            </p>
                        </form>
                    @else
                        <p class="text-xs mt-3" style="color: var(--color-state-ok)">
                            ● اتسجّلت مراجعة الإيصال {{ $request->receipt_reviewed_at?->diffForHumans() }}.
                        </p>
                    @endif
                @endif
            </div>

            @if ($request->status === 'pending_review' && $receiptReviewed && ! $duplicateOf)
                <form method="post" action="{{ route('admin.topups.approve', $request) }}" class="card p-4 space-y-3" id="approve-form">
                    @csrf
                    <h2 class="font-bold text-sm">الاعتماد بعد التحقّق</h2>

                    <label class="block text-sm">
                        <span class="block mb-1">مصدر القيمة</span>
                        <select name="mode" id="credit-mode" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="offer">من العروض (الكريدتس تتحسب تلقائيًّا)</option>
                            <option value="manual">قيمة يدويّة من الكريدتس</option>
                        </select>
                    </label>

                    <label class="block text-sm" id="offer-field">
                        <span class="block mb-1">العرض</span>
                        <select name="topup_offer_id" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($offers as $offer)
                                <option value="{{ $offer->id }}" @selected($request->topup_offer_id == $offer->id)>
                                    {{ $offer->label_ar }} — ادفع {{ rtrim(rtrim(number_format((float) $offer->pay_amount, 2), '0'), '.') }}
                                    ⟵ {{ rtrim(rtrim(number_format((float) $offer->credit_amount, 2), '0'), '.') }} كوينز
                                    ({{ $offer->bonus_percent > 0 ? '+' . rtrim(rtrim(number_format((float) $offer->bonus_percent, 2), '0'), '.') . '%' : 'بلا زيادة' }})
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-sm hidden" id="manual-field">
                        <span class="block mb-1">القيمة اليدويّة (كوينز)</span>
                        <input type="number" name="manual_amount" step="0.01" min="0"
                               class="w-full rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    </label>

                    <label class="block text-sm">
                        <span class="block mb-1">سبب مكتوب (إلزاميّ)</span>
                        <input type="text" name="reason" required minlength="3"
                               class="w-full rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                               placeholder="مثال: الإيصال مطابق والقيمة اتأكّدت من كشف الحساب">
                    </label>

                    {{-- ⭐ معاينة الرصيد قبل/بعد قبل التأكيد — تُحسَب في الخادم --}}
                    <div class="rounded-xl p-3 text-sm" style="background: var(--surface-sunken)">
                        <button type="button" id="preview-btn" class="text-xs underline">احسب الرصيد قبل/بعد</button>
                        <div id="preview-box" class="mt-2 hidden">
                            <span style="color: var(--text-muted)">قبل:</span> <strong id="prev-before">—</strong>
                            <span class="mx-2">⟵</span>
                            <span style="color: var(--text-muted)">بعد:</span> <strong id="prev-after" style="color: var(--color-brand-500)">—</strong>
                        </div>
                    </div>

                    <button class="btn w-full rounded-xl px-4 py-3 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">أكّد الاعتماد</button>
                </form>
            @endif

            @if ($request->status === 'pending_review')
                <form method="post" action="{{ route('admin.topups.cancel', $request) }}" class="card p-4 space-y-3">
                    @csrf
                    <h2 class="font-bold text-sm">إلغاء الطلب</h2>
                    <label class="block text-sm">
                        {{-- ⭐ سبب الإلغاء إلزاميّ ويصل للمستخدم بنصٍّ واضح --}}
                        <span class="block mb-1">سبب الإلغاء (إلزاميّ — بيوصل صاحب الطلب)</span>
                        <textarea name="reason" rows="2" required minlength="3"
                                  class="w-full rounded-xl px-3 py-2 text-sm"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                                  placeholder="مثال: الإيصال مش واضح — صوّره تاني بإضاءة أحسن"></textarea>
                    </label>
                    <button class="rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: color-mix(in srgb, var(--color-state-danger) 22%, transparent); color: var(--color-state-danger)">
                        إلغاء الطلب
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var mode = document.getElementById('credit-mode');
    if (!mode) { return; }

    var offerField = document.getElementById('offer-field');
    var manualField = document.getElementById('manual-field');

    mode.addEventListener('change', function () {
        offerField.classList.toggle('hidden', mode.value !== 'offer');
        manualField.classList.toggle('hidden', mode.value !== 'manual');
    });

    var token = document.querySelector('meta[name="csrf-token"]');

    document.getElementById('preview-btn').addEventListener('click', function () {
        var form = document.getElementById('approve-form');

        fetch('{{ route('admin.topups.preview', $request) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            body: JSON.stringify({
                mode: mode.value,
                topup_offer_id: form.querySelector('[name="topup_offer_id"]').value,
                manual_amount: form.querySelector('[name="manual_amount"]').value,
            }),
        }).then(function (r) { return r.json(); }).then(function (data) {
            var box = document.getElementById('preview-box');
            box.classList.remove('hidden');

            if (data.message) {
                box.textContent = data.message;
                return;
            }

            document.getElementById('prev-before').textContent = data.before;
            document.getElementById('prev-after').textContent = data.after;
        });
    });
})();
</script>
@endpush
