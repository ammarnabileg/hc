@extends('layouts.app')

@section('title', 'طرق التحويل وعروض الشحن')

@section('content')
    <x-page-header title="طرق التحويل وعروض الشحن"
                   subtitle="كلّ محتوى صفحة الشحن يتدار من هنا — بلا نصّ محروق."
                   :breadcrumbs="[
                       ['label' => 'لوحة الإدارة', 'url' => url('/admin')],
                       ['label' => 'طلبات الشحن', 'url' => route('admin.topups.index')],
                       ['label' => 'طرق التحويل والعروض'],
                   ]" />

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-4">
            <div class="card p-4">
                <h2 class="font-bold text-sm mb-3">طرق التحويل</h2>

                @forelse ($methods as $method)
                    <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                        <div>
                            <div class="font-semibold">{{ $method->name_ar }} <span class="text-xs" style="color: var(--text-muted)">({{ $types[$method->type] ?? $method->type }})</span></div>
                            <div class="text-xs" dir="ltr" style="color: var(--text-muted)">{{ $method->account_number }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-state-badge :state="$method->is_active ? 'ok' : 'idle'" :label="$method->is_active ? 'مفعَّلة' : 'موقوفة'" />
                            @can('topup.manage')
                                <form method="post" action="{{ route('admin.topups.methods.delete', $method) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-xs underline">حذف</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--text-muted)">مافيش طرق تحويل لسه — ضيف أوّل وسيلة.</p>
                @endforelse
            </div>

            @can('topup.manage')
                <form method="post" action="{{ route('admin.topups.methods.save') }}" class="card p-4 space-y-3">
                    @csrf
                    <h2 class="font-bold text-sm">+ طريقة تحويل</h2>
                    <label class="block text-sm">
                        <span class="block mb-1">النوع</span>
                        <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-form.input name="name_ar" label="اسم الوسيلة" required />
                    <x-form.input name="account_number" label="رقم الحساب / المحفظة" />
                    <x-form.input name="beneficiary_name" label="اسم المستفيد" />
                    <x-form.input name="sort_order" label="الترتيب" type="number" value="0" />
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" checked> مفعَّلة
                    </label>
                    <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">حفظ</button>
                </form>
            @endcan
        </div>

        <div class="space-y-4">
            @foreach (['manual' => ['التحويل اليدويّ', $manualOffers], 'gateway' => ['بوّابة الدفع', $gatewayOffers]] as $method => [$title, $offers])
                <div class="card p-4">
                    <h2 class="font-bold text-sm mb-3">عروض {{ $title }}</h2>

                    @forelse ($offers as $offer)
                        <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                            <div>
                                {{-- ⭐ العرض يعرض قيمته الحقيقيّة صراحةً — بلا مبالغة وبلا Dark Patterns (2.9) --}}
                                <div>ادفع {{ rtrim(rtrim(number_format((float) $offer->pay_amount, 2), '0'), '.') }}
                                    ⟵ تحصل على {{ rtrim(rtrim(number_format((float) $offer->credit_amount, 2), '0'), '.') }} كوينز</div>
                                <div class="text-xs" style="color: var(--text-muted)">
                                    {{ $offer->label_ar }}{{ $offer->bonus_percent > 0 ? ' · +' . rtrim(rtrim(number_format((float) $offer->bonus_percent, 2), '0'), '.') . '% إضافيّة' : '' }}
                                </div>
                            </div>
                            @can('topup.manage')
                                <form method="post" action="{{ route('admin.topups.offers.delete', $offer) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-xs underline">حذف</button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">مافيش عروض لسه.</p>
                    @endforelse
                </div>
            @endforeach

            @can('topup.manage')
                <form method="post" action="{{ route('admin.topups.offers.save') }}" class="card p-4 space-y-3">
                    @csrf
                    <h2 class="font-bold text-sm">+ عرض شحن</h2>
                    <label class="block text-sm">
                        <span class="block mb-1">الطريقة</span>
                        <select name="method" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="manual">التحويل اليدويّ</option>
                            <option value="gateway">بوّابة الدفع</option>
                        </select>
                    </label>
                    <x-form.input name="label_ar" label="عنوان العرض" required />
                    <x-form.input name="pay_amount" label="المبلغ المدفوع" type="number" required />
                    <x-form.input name="credit_amount" label="الكريدتس المستحقّة" type="number" required
                                  hint="نسبة الزيادة بتتحسب في الخادم وتظهر للمستخدم صراحةً." />
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_popular" value="1"> الأكثر شيوعًا</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> مفعَّل</label>
                    <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">حفظ العرض</button>
                </form>
            @endcan
        </div>
    </div>
@endsection
