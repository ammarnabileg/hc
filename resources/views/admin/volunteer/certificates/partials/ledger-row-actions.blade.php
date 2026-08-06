{{--
  إجراءات صفّ السجلّ (24.2): عرض · PDF · رابط التحقّق · إلغاء بمبرّر.
  «عرض» يفتح `certificates.partials.certificate-modal` المشترك — نفس النافذة
  التي يراها المتدرّب في مكتبته، لا نافذة موازية (مصدر واحد).
  المتغيّر المتوقَّع من المستدعي: $certificate.
--}}
<div class="flex items-center gap-2 text-xs flex-wrap">
    <button type="button" data-modal-open="cert-{{ $certificate->id }}" class="underline">{{ setting('admin.volunteer.certificates.ard', 'عرض') }}</button>

    <a href="{{ route('certificates.download', $certificate->code) }}" class="underline">{{ setting('admin.volunteer.certificates.pdf', 'PDF') }}</a>

    <a href="{{ route('verify.certificate', ['code' => $certificate->code]) }}" class="underline">{{ setting('admin.volunteer.certificates.rabt_althqq', 'رابط التحقّق') }}</a>

    @can('volunteer_certificates.edit')
        @if ($certificate->status === 'valid')
            <button type="button" class="underline" style="color: var(--color-state-danger)"
                    data-revoke data-id="{{ $certificate->id }}">{{ setting('admin.volunteer.certificates.ilgha', 'إلغاء') }}</button>
        @endif
    @endcan
</div>
