<div class="grid gap-4 lg:grid-cols-2">
    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">الإيرادات</h2>
        {!! $chart->line($data['series'], $data['series_prev']) !!}
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">أعلى مصادر الدخل</h2>
        {!! $chart->bars($data['sources']) !!}
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">الشحن بالطريقتين</h2>
        <div class="space-y-1 text-sm">
            <div class="flex justify-between"><span style="color: var(--text-muted)">تحويل يدويّ</span><strong>{{ $data['topup']['manual_total'] }}</strong></div>
            <div class="flex justify-between"><span style="color: var(--text-muted)">بوّابة الدفع</span><strong>{{ $data['topup']['gateway_total'] }}</strong></div>
            <div class="flex justify-between"><span style="color: var(--text-muted)">نسبة نجاح البوّابة</span><strong>{{ $data['topup']['gateway_success_rate'] }}%</strong></div>
            {{-- ⛔ متوسّط زمن المراجعة مؤشّر داخليّ ولا يُعلَن للمُرسِل (19.5-أ) --}}
            <div class="flex justify-between">
                <span style="color: var(--text-muted)">متوسّط زمن المراجعة (داخليّ)</span>
                <strong>{{ $data['topup']['avg_review_hours_internal'] }} ساعة</strong>
            </div>
        </div>
    </div>

    <div class="card p-4">
        <h2 class="font-bold text-sm mb-2">أثر عمولة الريفيرال</h2>
        <div class="text-2xl font-extrabold">{{ $data['referral_impact'] }}</div>
        <p class="text-xs mt-1" style="color: var(--text-muted)">
            بنسبة {{ rtrim(rtrim(number_format((float) setting('referral.commission_percent', 7), 2), '0'), '.') }}% من الإعدادات.
        </p>
    </div>
</div>

{{-- لا استردادات هنا (19.4) — والبديل تصحيحات أخطاء تقنيّة موثّقة --}}
<p class="text-xs mt-3" style="color: var(--text-muted)">
    مافيش عمود «استردادات» — لأنّ مافيش استرجاع نقديّ أصلًا؛ اللي بيتسجّل هو تصحيح الأخطاء التقنيّة.
</p>
