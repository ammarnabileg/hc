@php
    /**
     * بانر الموافقة على التتبّع (21.3-د) — شرطٌ لازم قبل تشغيل أيّ بكسل.
     * ولا يظهر إن كان التتبّع مطفأً كلّيًّا، ولا بعد اختيار المستخدم.
     */
    $trackingOn = (bool) setting('ads.tracking.enabled', false);
    $choice = auth()->user()?->tracking_consent ?? request()->cookie('tracking_consent');
@endphp

@if ($trackingOn && ! $choice)
    <div class="fixed inset-x-3 bottom-3 z-50 card p-4 md:max-w-md md:inset-x-auto md:end-4"
         role="region" aria-label="الموافقة على التتبّع">
        <p class="text-sm mb-3">{{ setting('ads.consent.banner_text', 'نستخدم ملفّات تعريف الارتباط لتحسين تجربتك. تقدر تقبل أو ترفض.') }}</p>

        <div class="flex flex-wrap gap-2">
            <form method="post" action="{{ route('consent.tracking') }}">
                @csrf<input type="hidden" name="choice" value="accepted">
                <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                        style="background: var(--color-brand-500); color:#04201c">أوافق</button>
            </form>
            <form method="post" action="{{ route('consent.tracking') }}">
                @csrf<input type="hidden" name="choice" value="rejected">
                <button class="btn rounded-xl px-4 py-2 text-sm"
                        style="background: var(--surface-sunken); color: var(--text)">أرفض</button>
            </form>
            <a href="{{ \Illuminate\Support\Facades\Route::has('settings.privacy') ? route('settings.privacy') : '#' }}"
               class="btn rounded-xl px-4 py-2 text-sm" style="color: var(--color-brand-500)">تخصيص</a>
        </div>
    </div>
@endif
