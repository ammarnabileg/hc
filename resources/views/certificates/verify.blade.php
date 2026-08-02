@php
    $data = (array) ($certificate?->data_snapshot ?? []);
    $appName = config('app.name');
    $subject = $data['certificate_name'] ?? $certificate?->certificate_type?->name_ar;
    $holder = $data['holder_name'] ?? $certificate?->user?->name;

    // بيانات SEO للصفحة المفهرسة (21.1-أ · 21.2-ب) — والفهرسة نفسها إعداد
    $indexCertificates = (bool) setting('growth.seo.index_certificates', true);
    $metaTitle = $certificate
        ? str_replace(['[الاسم]', '[الشهادة]', '[الكود]'], [$holder, $subject, $certificate->code],
            (string) setting('certificates.seo.meta_title', '[الاسم] — [الشهادة] · شهادة معتمدة'))
        : (string) setting('certificates.seo.index_title', 'التحقّق من الشهادة');
    $metaDescription = $certificate
        ? str_replace(['[الاسم]', '[الشهادة]', '[التاريخ]'], [$holder, $subject, $certificate->issued_at?->format(setting('certificates.render.date_format', 'Y/m/d'))],
            (string) setting('certificates.seo.meta_description', 'شهادة [الشهادة] الصادرة لـ[الاسم] بتاريخ [التاريخ] — تحقّق من صحّتها هنا.'))
        : (string) setting('certificates.seo.index_description', 'تحقّق من صحّة أيّ شهادة صادرة من المنصّة بكودها — بلا تسجيل دخول.');

    // Schema.org: EducationalOccupationalCredential لتظهر نتيجةً غنيّة (21.2-ب)
    $schema = $certificate ? [
        '@context' => 'https://schema.org',
        '@type' => 'EducationalOccupationalCredential',
        'name' => $subject,
        'identifier' => $certificate->code,
        'url' => route('verify.certificate', ['code' => $certificate->code]),
        'dateCreated' => $certificate->issued_at?->toDateString(),
        'expires' => $certificate->expired_at?->toDateString(),
        'credentialCategory' => $certificate->certificate_type?->name_ar,
        'recognizedBy' => [
            '@type' => 'Organization',
            'name' => $certificate->certificate_type?->accreditation?->name_ar ?? $appName,
        ],
        'about' => ['@type' => 'Person', 'name' => $holder],
        'image' => route('certificates.image', $certificate->code),
    ] : null;
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    @if ($certificate)
        <meta property="og:image" content="{{ route('certificates.image', $certificate->code) }}">
    @endif

    {{-- الفهرسة تُحترَم كإعداد لا كقرارٍ محروق في الكود (21.1-هـ) --}}
    <meta name="robots" content="{{ $indexCertificates && $certificate ? 'index, follow' : 'noindex, follow' }}">

    @if ($schema && $indexCertificates)
        {{-- Schema.org لتظهر نتيجةً غنيّة في محرّكات البحث (21.2-ب) --}}
        <script type="application/ld+json">
            {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
<main class="max-w-2xl mx-auto px-4 py-10">

    <header class="text-center mb-6">
        <div class="text-3xl mb-2" aria-hidden="true">🎓</div>
        <h1 class="text-2xl font-extrabold">{{ setting('certificates.verify.title', 'التحقّق من الشهادة') }}</h1>
        <p class="text-sm mt-2" style="color: var(--text-muted)">
            {{ setting('certificates.verify.intro', 'اكتب كود الشهادة وتأكّد من صحّتها وصلاحيّتها — بلا تسجيل دخول ولا حساب.') }}
        </p>
    </header>

    @if (session('status'))
        <x-toast :message="session('status')" />
    @endif

    {{-- البحث بالكود مباشرةً (8.1) — والـQR يفتح الصفحة والكود متعبّى ومتحقَّق تلقائيًّا --}}
    <form method="get" action="{{ route('verify.certificate') }}" class="card p-3 mb-6 flex flex-wrap items-center gap-2">
        <label class="flex-1 min-w-48">
            <span class="sr-only">{{ setting('certificates.labels.code', 'كود الشهادة') }}</span>
            <input type="search" name="code" value="{{ $code }}" required
                   placeholder="{{ setting('certificates.verify.placeholder', '#HC-2026-000001') }}"
                   class="w-full rounded-xl px-3 py-2 text-sm tabular-nums"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>
        <button type="submit" class="btn rounded-xl px-5 py-2 text-sm font-semibold motion-standard"
                style="background: var(--color-brand-500); color: #04201c">
            {{ setting('certificates.verify.submit', 'تحقّق') }}
        </button>
    </form>

    @if ($searched && ! $certificate)
        <div class="card p-6 text-center">
            <div class="flex justify-center mb-2"><x-state-badge state="danger" :label="setting('certificates.verify.not_found_badge', 'غير موجودة')" /></div>
            <p class="text-sm" style="color: var(--text-muted)">
                {{ setting('certificates.verify.not_found', 'مفيش شهادة بالكود ده في سجلّنا. راجع الكود، ولو شايف إنّ فيه مشكلة بلّغنا.') }}
            </p>
        </div>
    @elseif ($certificate)
        <article class="card p-5 animate-fadeup">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3 min-w-0">
                    @if (! empty($data['accreditation_logo']))
                        {{-- شعار جهة الاعتماد (12.5-هـ): منع تزوير — كلّ الاعتمادات موثّقة عندنا --}}
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($data['accreditation_logo']) }}" alt=""
                             class="w-12 h-12 rounded-xl object-contain" style="background: var(--surface-sunken)">
                    @endif
                    <div class="min-w-0">
                        <p class="text-xs" style="color: var(--text-muted)">{{ setting('certificates.labels.accredited_by', 'معتمدة من') }}</p>
                        <p class="font-bold truncate">{{ $data['accreditation_name'] ?? setting('certificates.accreditation.default_name', 'اعتماد المنصّة') }}</p>
                    </div>
                </div>

                {{-- الحالة بلون **ورمز** — واللون وحده لا يحمل المعنى (2.16-ب) --}}
                <x-state-badge :state="$state" :label="match ($certificate->status) {
                    'valid' => setting('certificates.status.valid_label', 'سارية'),
                    'expired' => setting('certificates.status.expired_label', 'منتهية'),
                    default => setting('certificates.status.revoked_label', 'ملغاة'),
                }" />
            </div>

            <img src="{{ route('certificates.image', $certificate->code) }}" loading="lazy"
                 alt="{{ $subject }}" class="w-full rounded-xl my-4" style="border: 1px solid var(--border)">

            <dl class="text-sm space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <dt style="color: var(--text-muted)">{{ setting('certificates.labels.holder', 'الحائز') }}</dt>
                    <dd class="font-semibold">{{ $holder }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt style="color: var(--text-muted)">{{ setting('certificates.labels.certificate', 'الشهادة') }}</dt>
                    <dd class="font-semibold">{{ $subject }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt style="color: var(--text-muted)">{{ setting('certificates.labels.issued_at', 'تاريخ الإصدار') }}</dt>
                    <dd class="font-semibold">{{ $certificate->issued_at?->format(setting('certificates.render.date_format', 'Y/m/d')) }}</dd>
                </div>
                @if (! empty($data['country']))
                    <div class="flex items-center justify-between gap-3">
                        <dt style="color: var(--text-muted)">{{ setting('certificates.labels.country', 'الدولة') }}</dt>
                        <dd class="font-semibold">{{ $data['country'] }}</dd>
                    </div>
                @endif
                <div class="flex items-center justify-between gap-3">
                    <dt style="color: var(--text-muted)">{{ setting('certificates.labels.code', 'الكود') }}</dt>
                    <dd class="font-semibold tabular-nums">#{{ $certificate->code }}</dd>
                </div>
            </dl>

            {{-- نصّ الحالة — و«منتهية» بنصّها المعتمَد: ليست ملغاة ولا مطعونًا في صحّتها (13.4-ق-و) --}}
            <p class="text-sm mt-4 p-3 rounded-xl" style="background: var(--surface-sunken); color: var(--text-muted)">
                {{ $statusText }}
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <a href="{{ route('certificates.download', $certificate->code) }}"
                   class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                   style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('certificates.labels.download_copy', 'تنزيل النسخة') }}
                </a>
                <a href="{{ route('certificates.print', $certificate->code) }}"
                   class="rounded-xl px-4 py-2 text-sm motion-standard" style="background: var(--surface-sunken)">
                    {{ setting('certificates.labels.print', 'نسخة للطباعة') }}
                </a>
                <button type="button" data-modal-open="report-modal"
                        class="rounded-xl px-4 py-2 text-sm motion-standard" style="background: var(--surface-sunken)">
                    {{ setting('certificates.labels.report', 'أبلغ عن شهادة مشبوهة') }}
                </button>
            </div>
        </article>

        <x-modal id="report-modal" :title="setting('certificates.labels.report', 'أبلغ عن شهادة مشبوهة')">
            <form method="post" action="{{ route('verify.certificate.report') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="code" value="{{ $certificate->code }}">

                <label class="block text-sm">
                    <span class="block mb-1" style="color: var(--text-muted)">{{ setting('certificates.labels.report_note', 'إيه اللي مريب؟') }}</span>
                    <textarea name="note" rows="4" required class="w-full rounded-xl px-3 py-2 text-sm"
                              style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                </label>

                <label class="block text-sm">
                    <span class="block mb-1" style="color: var(--text-muted)">{{ setting('certificates.labels.report_contact', 'وسيلة تواصل (اختياريّة)') }}</span>
                    <input type="text" name="contact" class="w-full rounded-xl px-3 py-2 text-sm"
                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                </label>

                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                        style="background: var(--color-brand-500); color: #04201c">
                    {{ setting('certificates.labels.report_submit', 'ابعت البلاغ') }}
                </button>
            </form>
        </x-modal>
    @endif

    <p class="text-xs text-center mt-8" style="color: var(--text-muted)">
        {{ setting('certificates.verify.footer', 'كلّ شهادة عندنا لها كود وQR وتوقيع رقميّ — والتحقّق مفتوح للجميع.') }}
    </p>
</main>
</body>
</html>
