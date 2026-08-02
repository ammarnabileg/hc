@php
    $data = (array) $certificate->data_snapshot;
    $verifyUrl = route('verify.certificate', ['code' => $certificate->code]);
    $type = $certificate->certificate_type;

    // نصّ منشور لينكدإن جاهز وقابل للتعديل (21.1-أ)
    $shareText = str_replace(
        ['[المسار]', '[الاسم]', '[الكود]'],
        [$data['certificate_name'] ?? ($type?->name_ar ?? ''), $data['holder_name'] ?? '', $certificate->code],
        (string) setting('growth.linkedin.share_text', 'أتممتُ [المسار] وحصلتُ على شهادة معتمدة.'),
    );

    // اللغتان (12.5-ب): يظهر المبدّل فقط إن كان النوع مفعَّلًا بنسختين
    $languages = array_values(array_filter([
        $type?->lang_ar_enabled ? 'ar' : null,
        $type?->lang_en_enabled ? 'en' : null,
    ]));
@endphp

<x-modal :id="'cert-'.$certificate->id" :title="$data['certificate_name'] ?? $certificate->code">
    <img src="{{ route('certificates.image', $certificate->code) }}" loading="lazy"
         alt="{{ $data['certificate_name'] ?? $certificate->code }}"
         class="w-full rounded-xl mb-4" style="border: 1px solid var(--border)">

    <dl class="text-sm space-y-2">
        <div class="flex items-center justify-between gap-3">
            <dt style="color: var(--text-muted)">{{ setting('certificates.labels.holder', 'الحائز') }}</dt>
            <dd class="font-semibold">{{ $data['holder_name'] ?? '' }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt style="color: var(--text-muted)">{{ setting('certificates.labels.code', 'الكود') }}</dt>
            <dd class="font-semibold tabular-nums">#{{ $certificate->code }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt style="color: var(--text-muted)">{{ setting('certificates.labels.issued_at', 'تاريخ الإصدار') }}</dt>
            <dd class="font-semibold">{{ $certificate->issued_at?->format(setting('certificates.render.date_format', 'Y/m/d')) }}</dd>
        </div>
        @if ($certificate->status === 'expired')
            <div class="flex items-center justify-between gap-3">
                <dt style="color: var(--text-muted)">{{ setting('certificates.labels.expired_at', 'انتهى العمل بها') }}</dt>
                <dd class="font-semibold">{{ $certificate->expired_at?->format(setting('certificates.render.date_format', 'Y/m/d')) }}</dd>
            </div>
        @endif
        <div class="flex items-center justify-between gap-3">
            <dt style="color: var(--text-muted)">{{ setting('certificates.labels.accreditation', 'الاعتماد') }}</dt>
            <dd class="font-semibold">{{ $type?->accreditation?->name_ar ?? setting('certificates.accreditation.default_name', 'اعتماد المنصّة') }}</dd>
        </div>
    </dl>

    @if (count($languages) > 1)
        <div class="mt-4">
            <p class="text-xs mb-2" style="color: var(--text-muted)">{{ setting('certificates.labels.language', 'لغة النسخة') }}</p>
            <div class="flex gap-2">
                @foreach ($languages as $language)
                    <a href="{{ route('certificates.download', $certificate->code) }}?lang={{ $language }}"
                       class="rounded-full px-3 py-1 text-xs motion-standard"
                       style="{{ $certificate->language === $language
                            ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                            : 'background: var(--surface-sunken); color: var(--text)' }}">
                        {{ $language === 'ar' ? setting('certificates.labels.arabic', 'عربيّة') : setting('certificates.labels.english', 'إنجليزيّة') }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <x-slot:footer>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('certificates.download', $certificate->code) }}"
               class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">
                {{ setting('certificates.labels.download', 'تحميل') }}
            </a>
            <a href="{{ $verifyUrl }}" class="rounded-xl px-4 py-2 text-sm motion-standard" style="background: var(--surface-sunken)">
                {{ setting('certificates.labels.verify_page', 'صفحة التحقّق') }}
            </a>
            <a href="{{ route('certificates.print', $certificate->code) }}" class="rounded-xl px-4 py-2 text-sm motion-standard"
               style="background: var(--surface-sunken)">
                {{ setting('certificates.labels.print', 'نسخة للطباعة') }}
            </a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($verifyUrl) }}"
               target="_blank" rel="noopener"
               class="rounded-xl px-4 py-2 text-sm motion-standard" style="background: var(--surface-sunken)">
                {{ setting('certificates.labels.share_linkedin', 'مشاركة على لينكدإن') }}
            </a>
        </div>

        {{-- نصّ المنشور جاهز وقابل للتعديل قبل النسخ (21.1-أ) --}}
        <label class="block mt-3 text-xs" style="color: var(--text-muted)">
            {{ setting('certificates.labels.share_text', 'نصّ المنشور — عدّله زيّ ما تحبّ') }}
            <textarea rows="3" class="w-full mt-1 rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $shareText }}

{{ $verifyUrl }}</textarea>
        </label>
    </x-slot:footer>
</x-modal>
