@php
    /**
     * بانر الموافقة على التتبّع (21.3-د) — **شرطٌ لازم قبل تشغيل أيّ بكسل**.
     *
     * ثلاثة خيارات واضحة: **قبول · رفض · تخصيص** — والتخصيص ليس زينةً:
     * يختار المستخدم الأغراض بعينها، وما لم يختره **لا يُرسَل عنه شيء** لا من
     * المتصفّح ولا من الخادم. ولا يظهر البانر أصلًا والتتبّع مطفأ كلّيًّا.
     */
    $adConsent = app(\App\Services\Ads\Consent::class);

    /*
     | ⭐ التأجيل (21.1-د · 21.3-د): «بلا Dark Patterns — البانر ليس حاصرًا».
     | كوكيٌّ مستقلّ عن كوكيّ القرار (`tracking_consent`) تمامًا — **لا يُقرَأ هنا
     | كقرار ولا يُكتَب كذلك أبدًا** — فطول عمره لا يعني موافقةً ولا رفضًا، بل
     | يعني فقط «لا تُزعجني الآن». وإن انتهت مدّته يعود البانر من تلقاء نفسه —
     | وإن كان القرار قد اتُّخِذ فعلًا (كوكي أو حساب) فـ`shouldAsk()` أصلًا لا
     | تسأل بغضّ النظر عن التأجيل.
     */
    $snoozedUntil = request()->cookie('tracking_consent_snoozed_until');
    $isSnoozed = false;

    if (is_string($snoozedUntil) && trim($snoozedUntil) !== '') {
        try {
            $isSnoozed = \Illuminate\Support\Carbon::parse($snoozedUntil)->isFuture();
        } catch (\Throwable) {
            $isSnoozed = false;
        }
    }

    $askConsent = $adConsent->shouldAsk() && ! $isSnoozed;
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
         role="region" aria-label="{{ setting('ads.consent.banner_aria', 'الموافقة على التتبّع') }}" data-consent-banner>
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

            {{-- ⭐ تأجيل — ليس قرارًا (21.1-د · 21.3-د): لا يُكتَب قبولٌ ولا رفض،
                 والبانر يعود بعد مدّة الإعداد. وهدف لمسٍ ≥44px (2.15-ج) --}}
            <form method="post" action="{{ route('consent.snooze') }}">
                @csrf
                <button data-consent-snooze
                        class="rounded-xl px-4 text-sm motion-standard"
                        style="min-height:44px; color: var(--text-muted)">
                    {{ setting('ads.consent.later_label', 'لاحقًا') }}
                </button>
            </form>
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
