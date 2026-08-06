{{--
  كروت الموبايل وحدها (24.2 · 13.1) — تُستعمَل في الصفحة الكاملة أوّل تحميل
  وفي ردّ Fragment للتمرير التدريجيّ، فالماركب واحد لا نسختان.
  المتغيّر المتوقَّع من المستدعي: $issued.
--}}
@use('App\Services\Admin\Volunteer\CertificateEligibility')

@foreach ($issued as $certificate)
    @php($summary = CertificateEligibility::rowSummary($certificate))
    <div class="card p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="font-semibold truncate">{{ $certificate->user?->name }}</div>
                <div class="text-xs" style="color: var(--text-muted)">#{{ $certificate->user?->code }} · <code>{{ $certificate->code }}</code></div>
            </div>
            <x-state-badge
                :state="match ($certificate->status) { 'valid' => 'ok', 'expired' => 'idle', default => 'danger' }"
                :label="match ($certificate->status) { 'valid' => setting('admin.volunteer.certificates.sarya', 'سارية'), 'expired' => setting('admin.volunteer.certificates.mnthya', 'منتهية'), default => setting('admin.volunteer.certificates.mlghaa', 'ملغاة') }" />
        </div>

        <div class="text-xs mt-2 space-y-1" style="color: var(--text-muted)">
            <div>{{ $certificate->certificate_type?->name_ar }} · {{ $summary['position_entity'] }}</div>
            <div>{{ $summary['period'] }} @if ($summary['team_size'] !== null) · {{ setting('admin.volunteer.certificates.frq', 'فريق') }} {{ $summary['team_size'] }} @endif</div>
            <div title="{{ $certificate->issued_at }}">{{ $certificate->issued_at?->format('Y-m-d') }} · {{ $certificate->language === 'en' ? setting('admin.volunteer.certificates.lang_en', 'إنجليزيّة') : setting('admin.volunteer.certificates.lang_ar', 'عربيّة') }}</div>
        </div>

        <div class="mt-3">
            @include('admin.volunteer.certificates.partials.ledger-row-actions', ['certificate' => $certificate])
        </div>
    </div>
@endforeach
