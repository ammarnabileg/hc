@extends('layouts.admin')

@section('title', setting('admin.store.topups.methods.trq_althwyl_warwd_alshhn', 'طرق التحويل وعروض الشحن'))

@section('content')
    <x-page-header :title="setting('admin.store.topups.methods.trq_althwyl_warwd_alshhn', 'طرق التحويل وعروض الشحن')"
                   :subtitle="setting('admin.store.topups.methods.kl_mhtwa_sfha_alshhn_ytdar_mn_hna_bla_ns', 'كلّ محتوى صفحة الشحن يتدار من هنا — بلا نصّ محروق.')"
                   :breadcrumbs="[
                       ['label' => setting('admin.store.topups.methods.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.store.topups.methods.tlbat_alshhn', 'طلبات الشحن'), 'url' => route('admin.topups.index')],
                       ['label' => setting('admin.store.topups.methods.trq_althwyl_walarwd', 'طرق التحويل والعروض')],
                   ]" />

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-4">
            <div class="card p-4">
                <h2 class="font-bold text-sm mb-3">{{ setting('admin.store.topups.methods.trq_althwyl', 'طرق التحويل') }}</h2>

                @forelse ($methods as $method)
                    <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                        <div>
                            <div class="font-semibold">{{ $method->name_ar }} <span class="text-xs" style="color: var(--text-muted)">({{ $types[$method->type] ?? $method->type }})</span></div>
                            <div class="text-xs" dir="ltr" style="color: var(--text-muted)">{{ $method->account_number }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-state-badge :state="$method->is_active ? 'ok' : 'idle'" :label="$method->is_active ? setting('admin.store.topups.methods.mfala', 'مفعَّلة') : setting('admin.store.topups.methods.mwqwfa', 'موقوفة')" />
                            @can('topup.manage')
                                <form method="post" action="{{ route('admin.topups.methods.delete', $method) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-xs underline">{{ setting('admin.store.topups.methods.hdhf', 'حذف') }}</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.store.topups.methods.mafysh_trq_thwyl_lsh_dyf_awl_wsyla', 'مافيش طرق تحويل لسه — ضيف أوّل وسيلة.') }}</p>
                @endforelse
            </div>

            @can('topup.manage')
                <form method="post" action="{{ route('admin.topups.methods.save') }}" class="card p-4 space-y-3">
                    @csrf
                    <h2 class="font-bold text-sm">{{ setting('admin.store.topups.methods.tryqa_thwyl', '+ طريقة تحويل') }}</h2>
                    <label class="block text-sm">
                        <span class="block mb-1">{{ setting('admin.store.topups.methods.alnwa', 'النوع') }}</span>
                        <select name="type" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-form.input name="name_ar" :label="setting('admin.store.topups.methods.asm_alwsyla', 'اسم الوسيلة')" required />
                    <x-form.input name="account_number" :label="setting('admin.store.topups.methods.rqm_alhsab_almhfza', 'رقم الحساب / المحفظة')" />
                    <x-form.input name="beneficiary_name" :label="setting('admin.store.topups.methods.asm_almstfyd', 'اسم المستفيد')" />
                    <x-form.input name="sort_order" :label="setting('admin.store.topups.methods.altrtyb', 'الترتيب')" type="number" value="0" />
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" checked> {{ setting('admin.store.topups.methods.mfala', 'مفعَّلة') }}
                    </label>
                    <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.store.topups.methods.hfz', 'حفظ') }}</button>
                </form>
            @endcan
        </div>

        <div class="space-y-4">
            @foreach (['manual' => [setting('admin.store.topups.methods.althwyl_alydwy', 'التحويل اليدويّ'), $manualOffers], 'gateway' => [setting('admin.store.topups.methods.bwaba_aldfa', 'بوّابة الدفع'), $gatewayOffers]] as $method => [$title, $offers])
                <div class="card p-4">
                    <h2 class="font-bold text-sm mb-3">{{ setting('admin.store.topups.methods.arwd', 'عروض') }} {{ $title }}</h2>

                    @forelse ($offers as $offer)
                        <div class="flex items-center justify-between gap-2 py-2 text-sm" style="border-top: 1px solid var(--border)">
                            <div>
                                {{-- ⭐ العرض يعرض قيمته الحقيقيّة صراحةً — بلا مبالغة وبلا Dark Patterns (2.9) --}}
                                <div>{!! strtr(setting('admin.store.topups.methods.adfa_v1_thsl_ala_v2_kwynz', 'ادفع :v1 ⟵ تحصل على :v2 كوينز'), [':v1' => e(rtrim(rtrim(number_format((float) $offer->pay_amount, 2), '0'), '.')), ':v2' => e(rtrim(rtrim(number_format((float) $offer->credit_amount, 2), '0'), '.'))]) !!}</div>
                                <div class="text-xs" style="color: var(--text-muted)">
                                    {{ $offer->label_ar }}{{ $offer->bonus_percent > 0 ? ' · +' . rtrim(rtrim(number_format((float) $offer->bonus_percent, 2), '0'), '.') . setting('admin.store.topups.methods.idafya', '% إضافيّة') : '' }}
                                </div>
                            </div>
                            @can('topup.manage')
                                <form method="post" action="{{ route('admin.topups.offers.delete', $offer) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-xs underline">{{ setting('admin.store.topups.methods.hdhf', 'حذف') }}</button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--text-muted)">{{ setting('admin.store.topups.methods.mafysh_arwd_lsh', 'مافيش عروض لسه.') }}</p>
                    @endforelse
                </div>
            @endforeach

            @can('topup.manage')
                <form method="post" action="{{ route('admin.topups.offers.save') }}" class="card p-4 space-y-3">
                    @csrf
                    <h2 class="font-bold text-sm">{{ setting('admin.store.topups.methods.ard_shhn', '+ عرض شحن') }}</h2>
                    <label class="block text-sm">
                        <span class="block mb-1">{{ setting('admin.store.topups.methods.altryqa', 'الطريقة') }}</span>
                        <select name="method" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="manual">{{ setting('admin.store.topups.methods.althwyl_alydwy', 'التحويل اليدويّ') }}</option>
                            <option value="gateway">{{ setting('admin.store.topups.methods.bwaba_aldfa', 'بوّابة الدفع') }}</option>
                        </select>
                    </label>
                    <x-form.input name="label_ar" :label="setting('admin.store.topups.methods.anwan_alard', 'عنوان العرض')" required />
                    <x-form.input name="pay_amount" :label="setting('admin.store.topups.methods.almblgh_almdfwa', 'المبلغ المدفوع')" type="number" required />
                    <x-form.input name="credit_amount" :label="setting('admin.store.topups.methods.alkrydts_almsthqa', 'الكريدتس المستحقّة')" type="number" required
                                  :hint="setting('admin.store.topups.methods.nsba_alzyada_btthsb_fy_alkhadm_wtzhr', 'نسبة الزيادة بتتحسب في الخادم وتظهر للمستخدم صراحةً.')" />
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_popular" value="1"> {{ setting('admin.store.topups.methods.alakthr_shywaa', 'الأكثر شيوعًا') }}</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> {{ setting('admin.store.topups.methods.mfal', 'مفعَّل') }}</label>
                    <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                            style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.store.topups.methods.hfz_alard', 'حفظ العرض') }}</button>
                </form>
            @endcan
        </div>
    </div>
@endsection
