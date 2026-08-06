{{--
  صفوف جدول سطح المكتب وحدها (24.2 · 13.1) — تُستعمَل في الصفحة الكاملة أوّل
  تحميل وفي ردّ Fragment للتمرير التدريجيّ، فالماركب واحد لا نسختان.
  المتغيّر المتوقَّع من المستدعي: $issued.
--}}
@use('App\Services\Admin\Volunteer\CertificateEligibility')

@foreach ($issued as $certificate)
    @php($summary = CertificateEligibility::rowSummary($certificate))
    <tr style="border-top: 1px solid var(--border)">
        <td class="px-4 py-3 font-mono whitespace-nowrap">{{ $certificate->code }}</td>
        <td class="px-4 py-3 whitespace-nowrap">
            <div class="font-semibold">{{ $certificate->user?->name }}</div>
            <div class="text-xs" style="color: var(--text-muted)">#{{ $certificate->user?->code }}</div>
        </td>
        <td class="px-4 py-3 whitespace-nowrap">{{ $certificate->certificate_type?->name_ar }}</td>
        <td class="px-4 py-3">{{ $summary['position_entity'] }}</td>
        <td class="px-4 py-3 whitespace-nowrap">{{ $summary['period'] }}</td>
        <td class="px-4 py-3 tabular-nums">{{ $summary['team_size'] ?? '—' }}</td>
        <td class="px-4 py-3 whitespace-nowrap" title="{{ $certificate->issued_at }}">{{ $certificate->issued_at?->format('Y-m-d') }}</td>
        <td class="px-4 py-3">{{ $certificate->language === 'en' ? setting('admin.volunteer.certificates.lang_en', 'إنجليزيّة') : setting('admin.volunteer.certificates.lang_ar', 'عربيّة') }}</td>
        <td class="px-4 py-3">
            <x-state-badge
                :state="match ($certificate->status) { 'valid' => 'ok', 'expired' => 'idle', default => 'danger' }"
                :label="match ($certificate->status) { 'valid' => setting('admin.volunteer.certificates.sarya', 'سارية'), 'expired' => setting('admin.volunteer.certificates.mnthya', 'منتهية'), default => setting('admin.volunteer.certificates.mlghaa', 'ملغاة') }" />
        </td>
        <td class="px-4 py-3">
            @include('admin.volunteer.certificates.partials.ledger-row-actions', ['certificate' => $certificate])
        </td>
    </tr>
@endforeach
