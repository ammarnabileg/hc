@php
    /** قالب «مودرن» — مدفوع بالتذاكر: عمودان، والنصّ يبقى قابلًا للقراءة آليًّا (ATS). */
    $profile = $data['profile'] ?? [];
    $identity = $pulled['profile'] ?? [];
    $skills = collect(explode(',', (string) ($data['skills'] ?? '')))->map(fn ($s) => trim($s))->filter()->values();
    $line = fn (array $row) => trim(($row['from'] ?? '').' — '.(($row['current'] ?? null) ? setting('cv.until_now_label', 'حتى الآن') : ($row['to'] ?? '')), ' —');
@endphp

<div class="sheet" style="padding: 0">
    <div style="display: flex; min-block-size: 297mm">
        <aside style="inline-size: 62mm; background: #05423a; color: #e8f5f2; padding: 12mm 8mm">
            <h1 style="font-size: 16pt; color: #fff">{{ $identity['name'] ?? setting('cv.sheet.untitled', 'سيرتي الذاتيّة') }}</h1>
            <p style="color: #b8fff4">{{ $profile['job_title'] ?? '' }}</p>

            <h2 style="color: #7cf7e6">{{ setting('cv.section.contact_label', 'التواصل') }}</h2>
            <p>{{ $profile['email'] ?? ($identity['email'] ?? '') }}</p>
            <p>{{ $profile['phone'] ?? ($identity['phone'] ?? '') }}</p>
            <p>{{ collect([$profile['city'] ?? ($identity['governorate'] ?? null), $identity['country'] ?? null])->filter()->implode('، ') }}</p>

            @if ($skills->isNotEmpty())
                <h2 style="color: #7cf7e6">{{ setting('cv.section.skills_label', 'المهارات') }}</h2>
                <ul>@foreach ($skills as $skill)<li>{{ $skill }}</li>@endforeach</ul>
            @endif

            @if (! empty($data['languages']))
                <h2 style="color: #7cf7e6">{{ setting('cv.section.languages_label', 'اللغات') }}</h2>
                <ul>
                    @foreach ($data['languages'] as $row)
                        <li>{{ $row['language'] ?? '' }}{{ ! empty($row['level']) ? ' — '.$row['level'] : '' }}</li>
                    @endforeach
                </ul>
            @endif
        </aside>

        <main style="flex: 1; padding: 12mm 10mm">
            @if (! empty($profile['summary']))
                <h2>{{ setting('cv.section.summary_label', 'نبذة مهنيّة') }}</h2>
                <p>{{ $profile['summary'] }}</p>
            @endif

            @if (! empty($data['experience']))
                <h2>{{ setting('cv.section.experience_label', 'الخبرة العمليّة') }}</h2>
                @foreach ($data['experience'] as $row)
                    <div class="entry">
                        <div class="row">
                            <strong>{{ $row['title'] ?? '' }}{{ ! empty($row['company']) ? ' — '.$row['company'] : '' }}</strong>
                            <span class="muted">{{ $line($row) }}</span>
                        </div>
                        @if (! empty($row['description']))<p class="muted">{{ $row['description'] }}</p>@endif
                    </div>
                @endforeach
            @endif

            @if (! empty($data['education']))
                <h2>{{ setting('cv.section.education_label', 'رحلة التعلّم') }}</h2>
                @foreach ($data['education'] as $row)
                    <div class="entry">
                        <div class="row">
                            <strong>{{ $row['degree'] ?? '' }}{{ ! empty($row['institution']) ? ' — '.$row['institution'] : '' }}</strong>
                            <span class="muted">{{ $line($row) }}</span>
                        </div>
                    </div>
                @endforeach
            @endif

            @if (($pulled['certificates'] ?? collect())->isNotEmpty())
                <h2>{{ setting('cv.section.certificates_label', 'الشهادات') }}</h2>
                <ul>
                    @foreach ($pulled['certificates'] as $certificate)
                        <li>{{ $certificate->certificate_type?->name_ar }}
                            <span class="muted">— {{ $certificate->issued_at?->translatedFormat('F Y') }} · {{ $certificate->code }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </main>
    </div>
</div>
