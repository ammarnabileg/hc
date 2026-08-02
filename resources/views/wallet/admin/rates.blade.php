@extends('layouts.app')

@section('title', 'أسعار الصرف والرسوم')

@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp

@section('content')
    <x-page-header
        title="🔒 أسعار الصرف والرسوم"
        subtitle="مصدر الحقيقة الوحيد لكلّ رقم ماليّ في المحفظة — لمالك المنصّة وحده."
        :breadcrumbs="[['label' => 'المحفظة', 'url' => route('wallet.index')], ['label' => 'أسعار الصرف']]" />

    @if ($errors->has('rates'))
        <x-toast :message="$errors->first('rates')" state="danger" />
    @endif

    {{-- الأسعار المعتمَدة الآن — سطر واحد يقول كلّ شيء قبل أيّ تعديل --}}
    <section class="grid grid-cols-1 md:grid-cols-3 gap-3">
        @foreach ($rates as $rate)
            <x-kpi :label="$rate['label']" :value="$rate['value']" icon="💱" />
        @endforeach
    </section>

    {{-- ⭐ معاينة حيّة بنفس خدمات التنفيذ: يرى المالك أثر الرقم قبل أن يحفظه --}}
    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">معاينة لحظيّة</h2>
        <div class="grid gap-2 text-sm md:grid-cols-3">
            <div>
                <div style="color: var(--text-muted)">حوالة {{ $num($previewAmount) }} كوين</div>
                <div class="mt-1">رسوم {{ $num($transferPreview['fee']) }} ⟵ يستلم <strong>{{ $num($transferPreview['net']) }}</strong></div>
            </div>
            <div>
                <div style="color: var(--text-muted)">تحويل {{ $num($previewAmount) }} كوين ← تذاكر</div>
                <div class="mt-1">رسوم {{ $num($exchangePreview['fee']) }} ⟵ يستلم <strong>{{ $num($exchangePreview['credited']) }}</strong></div>
            </div>
            <div>
                <div style="color: var(--text-muted)">سحب ${{ $num($withdrawPreview['amount']) }}</div>
                <div class="mt-1">رسوم ${{ $num($withdrawPreview['fee']) }} ⟵ يوصله <strong>${{ $num($withdrawPreview['net']) }}</strong></div>
            </div>
        </div>
    </section>

    @foreach ($catalog as $key => $group)
        <section class="card p-4 md:p-5 mt-4">
            <h2 class="font-bold">{{ $group['label'] }}</h2>
            <p class="text-xs mt-1 mb-4" style="color: var(--text-muted)">{{ $group['hint'] }}</p>

            <div class="space-y-4">
                @foreach ($group['fields'] as $field => [$label, $type, $default])
                    <form method="POST" action="{{ route('admin.wallet.rates.save') }}"
                          class="grid gap-2 md:grid-cols-12 md:items-end">
                        @csrf
                        <input type="hidden" name="key" value="{{ $field }}">

                        <label class="block md:col-span-4">
                            <span class="block text-sm mb-1">{{ $label }}</span>
                            <input name="value" value="{{ $settings[$field]->value }}"
                                   placeholder="{{ $settings[$field]->default_value }}"
                                   class="w-full rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </label>

                        {{-- ⭐ سبب إلزاميّ مع كلّ تغيير ماليّ — يدخل سجلّ التدقيق (2.13-د) --}}
                        <label class="block md:col-span-6">
                            <span class="block text-sm mb-1">سبب التعديل</span>
                            <input name="reason" required minlength="3"
                                   placeholder="اكتب ليه بتغيّر الرقم ده"
                                   class="w-full rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </label>

                        <div class="md:col-span-2">
                            <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c">احفظ</button>
                        </div>

                        <p class="md:col-span-12 text-xs" style="color: var(--text-muted)">
                            المفتاح: <code>{{ $field }}</code> · الافتراضيّ: {{ $settings[$field]->default_value }}
                        </p>
                    </form>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection
