@extends('layouts.admin')

@section('title', setting('wallet.rates.title', 'أسعار الصرف والرسوم'))

@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp

@section('content')
    <x-page-header
        :title="setting('wallet.rates.title', 'أسعار الصرف والرسوم')"
        :subtitle="setting('wallet.rates.subtitle', 'مصدر الحقيقة الوحيد لكلّ رقم ماليّ في المحفظة — لمالك المنصّة وحده.')"
        :breadcrumbs="[['label' => setting('wallet.rates.breadcrumb_root', 'المحفظة'), 'url' => route('wallet.index')], ['label' => setting('wallet.rates.breadcrumb_self', 'أسعار الصرف')]]" />

    @if ($errors->has('rates'))
        <x-toast :message="$errors->first('rates')" state="danger" />
    @endif

    {{-- الأسعار المعتمَدة الآن — سطر واحد يقول كلّ شيء قبل أيّ تعديل --}}
    <section class="grid grid-cols-1 md:grid-cols-3 gap-3">
        @foreach ($rates as $rate)
            <x-kpi :label="$rate['label']" :value="$rate['value']" icon="transaction" />
        @endforeach
    </section>

    {{-- ⭐ معاينة حيّة بنفس خدمات التنفيذ: يرى المالك أثر الرقم قبل أن يحفظه --}}
    <section class="card p-4 md:p-5 mt-4">
        <h2 class="font-bold mb-3">{{ setting('wallet.rates.preview_title', 'معاينة لحظيّة') }}</h2>
        <div class="grid gap-2 text-sm md:grid-cols-3">
            <div>
                <div style="color: var(--text-muted)">{{ str_replace(':amount', $num($previewAmount), (string) setting('wallet.rates.preview_transfer', 'حوالة :amount كوين')) }}</div>
                <div class="mt-1">{!! str_replace([':fee', ':net'], [e($num($transferPreview['fee'])), '<strong>'.e($num($transferPreview['net'])).'</strong>'], e(setting('wallet.rates.preview_fee_net', 'رسوم :fee ⟵ يستلم :net'))) !!}</div>
            </div>
            <div>
                <div style="color: var(--text-muted)">{{ str_replace(':amount', $num($previewAmount), (string) setting('wallet.rates.preview_exchange', 'تحويل :amount كوين ← تذاكر')) }}</div>
                <div class="mt-1">{!! str_replace([':fee', ':net'], [e($num($exchangePreview['fee'])), '<strong>'.e($num($exchangePreview['credited'])).'</strong>'], e(setting('wallet.rates.preview_fee_net', 'رسوم :fee ⟵ يستلم :net'))) !!}</div>
            </div>
            <div>
                <div style="color: var(--text-muted)">{{ str_replace(':amount', $num($withdrawPreview['amount']), (string) setting('wallet.rates.preview_withdraw', 'سحب $:amount')) }}</div>
                <div class="mt-1">{!! str_replace([':fee', ':net'], [e($num($withdrawPreview['fee'])), '<strong>$'.e($num($withdrawPreview['net'])).'</strong>'], e(setting('wallet.rates.preview_withdraw_fee_net', 'رسوم $:fee ⟵ يوصله :net'))) !!}</div>
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
                            <span class="block text-sm mb-1">{{ setting('wallet.rates.reason_label', 'سبب التعديل') }}</span>
                            <input name="reason" required minlength="3"
                                   placeholder="{{ setting('wallet.rates.reason_placeholder', 'اكتب ليه بتغيّر الرقم ده') }}"
                                   class="w-full rounded-xl px-3 py-2 text-sm"
                                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </label>

                        <div class="md:col-span-2">
                            <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                                    style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.rates.save_action', 'احفظ') }}</button>
                        </div>

                        <p class="md:col-span-12 text-xs" style="color: var(--text-muted)">
                            {!! str_replace([':key', ':default'], ['<code>'.e($field).'</code>', e($settings[$field]->default_value)], e(setting('wallet.rates.field_meta', 'المفتاح: :key · الافتراضيّ: :default'))) !!}
                        </p>
                    </form>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection
