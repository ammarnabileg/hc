@extends('layouts.app')

@section('title', setting('wallet.topup.title', 'شحن الحساب'))

@php
    $tabs = [];

    if ($manualEnabled) {
        $tabs[] = ['key' => 'manual', 'label' => setting('wallet.topup.tab_manual', 'تحويل يدويّ'), 'url' => route('wallet.topup', ['tab' => 'manual'])];
    }

    if ($gatewayEnabled) {
        $tabs[] = ['key' => 'gateway', 'label' => setting('wallet.topup.tab_gateway', 'بوّابة الدفع'), 'url' => route('wallet.topup', ['tab' => 'gateway'])];
    }

    $typeLabels = [
        'bank' => setting('wallet.topup.method_type_bank', 'حساب بنكيّ'),
        'wallet' => setting('wallet.topup.method_type_wallet', 'محفظة موبايل'),
        'instapay' => setting('wallet.topup.method_type_instapay', 'إنستا باي'),
        'other' => setting('wallet.topup.method_type_other', 'أخرى'),
    ];
@endphp

@section('content')
    <x-page-header
        :title="setting('wallet.topup.title', 'شحن الحساب')"
        :subtitle="setting('wallet.topup.subtitle', 'اختر طريقتك: تحويل يدويّ بإيصال، أو دفع مباشر من البوّابة.')"
        :breadcrumbs="[['label' => setting('wallet.index.breadcrumb_root', 'المحفظة'), 'url' => route('wallet.index')], ['label' => setting('wallet.topup.title', 'شحن الحساب')]]">
        <x-slot:action>
            @can('topup.list')
                <a href="{{ route('wallet.topup.requests') }}"
                   class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--surface-raised); color: var(--text)">{{ setting('wallet.topup_requests.title', 'طلبات الشحن') }}</a>
            @endcan
        </x-slot:action>
    </x-page-header>

    {{-- تابان في صفحة واحدة، والافتراضيّ من لوحة الأدمن (19.5) — وتحميل كسول لكلٍّ (2.15-د) --}}
    <x-tabs :tabs="$tabs" :current="$tab" />

    @if ($tab === 'manual' && $manualEnabled)
        {{-- ---------------------------------------------- التحويل اليدويّ --}}

        <section class="mb-5">
            <h2 class="font-bold mb-3">{{ setting('wallet.topup.methods_title', 'طرق التحويل') }}</h2>

            @if ($transferMethods->isEmpty())
                <x-empty :message="setting('wallet.topup.methods_empty', 'لسّه مافيش طرق تحويل متاحة — جرّب بوّابة الدفع.')"
                         :action="setting('wallet.topup.tab_gateway', 'بوّابة الدفع')" :href="route('wallet.topup', ['tab' => 'gateway'])" />
            @else
                <div class="grid md:grid-cols-2 gap-3">
                    @foreach ($transferMethods as $method)
                        <div class="card p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-semibold">{{ $method->name_ar }}</div>
                                <span class="text-xs rounded-full px-2 py-0.5"
                                      style="background: var(--surface-sunken); color: var(--text-muted)">{{ $typeLabels[$method->type] ?? $method->type }}</span>
                            </div>

                            @if ($method->account_number)
                                <div class="mt-3 flex items-center gap-2">
                                    <code class="flex-1 rounded-lg px-3 py-2 text-sm select-all"
                                          style="background: var(--surface-sunken)">{{ $method->account_number }}</code>
                                    {{-- ردّ فوريّ لكلّ فعل: «اتنسخ ✓» (2.17-ب) --}}
                                    <button type="button" class="btn rounded-lg px-3 py-2 text-xs font-semibold motion-standard"
                                            style="background: var(--color-brand-500); color: #04201c"
                                            data-copy="{{ $method->account_number }}">{{ setting('wallet.topup.copy_action', 'نسخ') }}</button>
                                </div>
                            @endif

                            @if ($method->beneficiary_name)
                                <div class="mt-2 text-sm">{{ str_replace(':name', $method->beneficiary_name, (string) setting('wallet.topup.beneficiary', 'المستفيد: :name')) }}</div>
                            @endif

                            @if ($method->notes)
                                <p class="mt-2 text-xs" style="color: var(--text-muted)">{{ $method->notes }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($manualOffers->isNotEmpty())
            <section class="mb-5">
                <h2 class="font-bold mb-3">{{ setting('wallet.topup.offers_title', 'عروض الشحن') }}</h2>
                <div class="grid md:grid-cols-3 gap-3">
                    @foreach ($manualOffers as $offer)
                        {{-- ⭐ العرض بقيمته الحقيقيّة صراحةً — بلا مبالغة وبلا Dark Patterns (19.5-ب-2) --}}
                        <div class="card p-4">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm" style="color: var(--text-muted)">{{ $offer->label_ar }}</span>
                                @if ($offer->is_popular)
                                    <span class="text-xs rounded-full px-2 py-0.5"
                                          style="background: var(--surface-sunken); color: var(--text-muted)">{{ setting('wallet.topup.popular_badge', 'الأكثر شيوعًا') }}</span>
                                @endif
                            </div>
                            <p class="mt-2 font-bold">
                                {{ str_replace([':pay', ':credit'], [number_format((float) $offer->pay_amount), number_format((float) $offer->credit_amount)], (string) setting('wallet.topup.offer_line', 'ادفع :pay ← تحصل على :credit كوينز')) }}
                                @if ((float) $offer->bonus_percent > 0)
                                    <span style="color: var(--color-state-ok)">(+{{ rtrim(rtrim(number_format((float) $offer->bonus_percent, 2), '0'), '.') }}%)</span>
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($pending)
            {{-- ⭐ قفل: طلب معلَّق واحد في المرّة (19.5-ب-5) — بلا أيّ إعلان لمهلة مراجعة --}}
            <section class="card p-5">
                <div class="flex items-center gap-2">
                    <x-state-badge state="warn" :label="setting('wallet.topup_requests.state_pending', 'قيد التحقّق')" />
                    <span class="font-semibold">{{ str_replace(':number', $pending->number, (string) setting('wallet.topup.pending_title', 'طلبك رقم :number تحت التحقّق')) }}</span>
                </div>
                <p class="mt-2 text-sm" style="color: var(--text-muted)">
                    {{ setting('wallet.topup.pending_note', 'بنراجع طلبك الحاليّ الأوّل. تقدر تتابع حالته، وأوّل ما يخلص تبعت التالي.') }}
                </p>
                <a href="{{ route('wallet.topup.requests') }}"
                   class="btn inline-flex items-center mt-4 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.topup.track_action', 'تابع طلبك') }}</a>
            </section>
        @else
            <section class="card p-5">
                <h2 class="font-bold mb-4">{{ setting('wallet.topup.form_title', 'بيانات التحويل') }}</h2>

                <form method="post" action="{{ route('wallet.topup.manual') }}" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-4">
                    @csrf

                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('wallet.topup.offer_label', 'عرض الشحن') }}</span>
                        <select name="topup_offer_id" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">{{ setting('wallet.topup.offer_other', 'مبلغ آخر') }}</option>
                            @foreach ($manualOffers as $offer)
                                <option value="{{ $offer->id }}"
                                        @selected(old('topup_offer_id', $resend?->topup_offer_id) == $offer->id)>
                                    {{ str_replace([':pay', ':credit'], [number_format((float) $offer->pay_amount), number_format((float) $offer->credit_amount)], (string) setting('wallet.topup.offer_option', 'ادفع :pay ← :credit كوينز')) }}
                                </option>
                            @endforeach
                        </select>
                        @error('topup_offer_id')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>@enderror
                    </label>

                    <x-form.input name="transferred_amount" :label="setting('wallet.topup.amount_label', 'القيمة المحوَّلة')" type="number" step="0.01"
                                  :value="$resend?->transferred_amount" />

                    <x-form.input name="paid_at" :label="setting('wallet.topup.paid_at_label', 'وقت وتاريخ الدفع')" type="datetime-local"
                                  :value="$resend?->paid_at?->format('Y-m-d\TH:i')" />

                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('wallet.topup.method_label', 'طريقة التحويل') }}</span>
                        <select name="transfer_method_id" class="w-full rounded-xl px-3 py-2 text-sm"
                                style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="">{{ setting('wallet.topup.method_placeholder', 'اختر…') }}</option>
                            @foreach ($transferMethods as $method)
                                <option value="{{ $method->id }}"
                                        @selected(old('transfer_method_id', $resend?->transfer_method_id) == $method->id)>{{ $method->name_ar }}</option>
                            @endforeach
                        </select>
                        @error('transfer_method_id')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>@enderror
                    </label>

                    {{-- رقم التواصل مملوء افتراضيًّا برقمه المسجَّل وقابل للتعديل (19.5-ب-3) --}}
                    <x-form.input name="contact_phone" :label="setting('wallet.topup.contact_phone_label', 'رقم التواصل')" :value="$defaultPhone" />

                    <label class="block">
                        <span class="block text-sm mb-1">{{ setting('wallet.topup.receipt_label', 'صورة الإيصال') }}</span>
                        <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" required
                               class="w-full rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ setting('wallet.topup.receipt_hint', 'مرفق إلزاميّ — صورة أو PDF.') }}</span>
                        @error('receipt')<span class="block text-xs mt-1" style="color: var(--color-state-danger)">{{ $message }}</span>@enderror
                    </label>

                    <div class="md:col-span-2 flex items-center justify-between gap-3 flex-wrap">
                        <p class="text-xs" style="color: var(--text-muted)">
                            {{ setting('wallet.topup.no_refund_note', 'الشحن يزيد رصيد الكوينز، ولا استرجاع نقديّ — الرصيد يفضل في محفظتك تشتري بيه اللي يعجبك.') }}
                        </p>
                        <button type="submit" class="btn rounded-xl px-5 py-3 text-sm font-bold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.topup.submit_action', 'ابعت الطلب') }}</button>
                    </div>
                </form>
            </section>
        @endif

    @elseif ($tab === 'gateway' && $gatewayEnabled)
        {{-- ---------------------------------------------- بوّابة الدفع --}}

        @if ($gatewayOffers->isEmpty())
            <x-empty :message="setting('wallet.topup.gateway_empty', 'لسّه مافيش عروض على البوّابة — جرّب التحويل اليدويّ.')"
                     :action="setting('wallet.topup.gateway_empty_action', 'التحويل اليدويّ')" :href="route('wallet.topup', ['tab' => 'manual'])" />
        @else
            <div class="grid md:grid-cols-3 gap-3">
                @foreach ($gatewayOffers as $offer)
                    <form method="post" action="{{ route('wallet.topup.gateway') }}" class="card p-4 flex flex-col">
                        @csrf
                        <input type="hidden" name="topup_offer_id" value="{{ $offer->id }}">

                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm" style="color: var(--text-muted)">{{ $offer->label_ar }}</span>
                            @if ($offer->is_popular)
                                <span class="text-xs rounded-full px-2 py-0.5"
                                      style="background: var(--surface-sunken); color: var(--text-muted)">{{ setting('wallet.topup.popular_badge', 'الأكثر شيوعًا') }}</span>
                            @endif
                        </div>

                        <p class="mt-2 font-bold flex-1">
                            {{ str_replace([':pay', ':credit'], [number_format((float) $offer->pay_amount), number_format((float) $offer->credit_amount)], (string) setting('wallet.topup.offer_line', 'ادفع :pay ← تحصل على :credit كوينز')) }}
                            @if ((float) $offer->bonus_percent > 0)
                                <span style="color: var(--color-state-ok)">(+{{ rtrim(rtrim(number_format((float) $offer->bonus_percent, 2), '0'), '.') }}%)</span>
                            @endif
                        </p>

                        <button type="submit" class="btn mt-4 rounded-xl px-4 py-2 text-sm font-bold motion-standard"
                                style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.topup.pay_now_action', 'ادفع دلوقتي') }}</button>
                    </form>
                @endforeach
            </div>

            <p class="mt-4 text-xs" style="color: var(--text-muted)">
                {{ setting('wallet.topup.gateway_note', 'بنحوّلك لصفحة الدفع الآمنة، ورصيدك بيتحدّث لمّا يوصلنا تأكيد الدفع من البوّابة.') }}
            </p>
        @endif
    @else
        <x-empty :message="setting('wallet.topup.disabled_message', 'طريقة الشحن دي متوقّفة حاليًّا.')"
                 :action="setting('wallet.topup.disabled_action', 'ارجع للمحفظة')" :href="route('wallet.index')" />
    @endif
@endsection

@push('scripts')
    @php
        $copyWords = [
            'done' => (string) setting('wallet.topup.copy_done', 'اتنسخ ✓'),
            'manual' => (string) setting('wallet.topup.copy_manual', 'انسخه يدويًّا'),
        ];
    @endphp
    <script>
        const copyWords = @json($copyWords);
        // نسخ رقم الحساب بضغطة — وردٌّ فوريّ يطمّن المستخدم أنّه اتنسخ (2.17-ب)
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-copy]');
            if (!btn) return;

            const original = btn.textContent;
            try {
                await navigator.clipboard.writeText(btn.dataset.copy);
                btn.textContent = copyWords.done;
            } catch {
                btn.textContent = copyWords.manual;
            }
            setTimeout(() => { btn.textContent = original; }, 1500);
        });
    </script>
@endpush
