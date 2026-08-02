@php
    /**
     * بانر الموافقة على التتبّع (21.3-د) — **شرطٌ لازم قبل تشغيل أيّ بكسل**.
     *
     * ثلاثة خيارات واضحة: **قبول · رفض · تخصيص** — والتخصيص ليس زينةً:
     * يختار المستخدم الأغراض بعينها، وما لم يختره **لا يُرسَل عنه شيء** لا من
     * المتصفّح ولا من الخادم. ولا يظهر البانر أصلًا والتتبّع مطفأ كلّيًّا.
     */
    $adConsent = app(\App\Services\Ads\Consent::class);
    $askConsent = $adConsent->shouldAsk();
    $privacyUrl = \Illuminate\Support\Facades\Route::has('settings.privacy') ? route('settings.privacy') : null;
    $purposeLabels = [
        'ads' => setting('ads.consent.purpose_ads', 'قياس الإعلانات وإعادة الاستهداف'),
        'analytics' => setting('ads.consent.purpose_analytics', 'قياس داخليّ لتحسين المنصّة'),
    ];
@endphp

{{-- ⭐ البكسل نفسه — ولا يُحقَن إلّا بعد موافقةٍ صريحة (الحارس داخل القالب) --}}
@include('growth.partials.tracking')

@if ($askConsent)
    <div class="fixed inset-x-3 bottom-3 z-50 card p-4 md:max-w-md md:inset-x-auto md:end-4"
         role="region" aria-label="الموافقة على التتبّع" data-consent-banner>
        <p class="text-sm mb-3">{{ setting('ads.consent.banner_text', 'نستخدم ملفّات تعريف الارتباط لتحسين تجربتك. تقدر تقبل أو ترفض.') }}</p>

        <div class="flex flex-wrap gap-2">
            <form method="post" action="{{ route('consent.tracking') }}">
                @csrf<input type="hidden" name="choice" value="accepted">
                <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color:#04201c">{{ setting('ads.consent.accept_label', 'أوافق') }}</button>
            </form>

            <form method="post" action="{{ route('consent.tracking') }}">
                @csrf<input type="hidden" name="choice" value="rejected">
                <button class="btn rounded-xl px-4 py-2 text-sm"
                        style="background: var(--surface-sunken); color: var(--text)">{{ setting('ads.consent.reject_label', 'أرفض') }}</button>
            </form>

            <button type="button" data-consent-customize
                    class="rounded-xl px-4 py-2 text-sm motion-standard" style="color: var(--color-brand-500)">
                {{ setting('ads.consent.custom_label', 'تخصيص') }}
            </button>
        </div>

        {{-- لوحة التخصيص: غرضٌ غرض، ولا شيء مفعَّل مقدَّمًا — الصمت ليس موافقة --}}
        <form method="post" action="{{ route('consent.tracking') }}" class="mt-3 hidden" data-consent-panel>
            @csrf<input type="hidden" name="choice" value="custom">

            @foreach ($purposeLabels as $purpose => $label)
                <label class="flex items-center gap-2 text-sm py-1">
                    <input type="checkbox" name="scopes[]" value="{{ $purpose }}" style="accent-color: var(--color-brand-500)">
                    <span>{{ $label }}</span>
                </label>
            @endforeach

            <button class="btn mt-2 rounded-xl px-4 py-2 text-sm font-semibold"
                    style="background: var(--color-brand-500); color:#04201c">{{ setting('ads.consent.save_label', 'احفظ اختياري') }}</button>
        </form>

        @if ($privacyUrl)
            {{-- حقّ السحب في أيّ وقت من إعدادات الخصوصيّة والأمان (21.3-د · 24.5) --}}
            <p class="text-xs mt-3" style="color: var(--text-muted)">
                <a href="{{ $privacyUrl }}" class="hover:underline">{{ setting('ads.consent.policy_link', 'سياسة الخصوصيّة وحقّ السحب') }}</a>
            </p>
        @endif
    </div>

    <script>
        document.querySelector('[data-consent-customize]')?.addEventListener('click', function () {
            document.querySelector('[data-consent-panel]')?.classList.toggle('hidden');
        });
    </script>
@endif
