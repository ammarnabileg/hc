@php
    /** قالب «كلاسيك» — المجّانيّ (21.2-ج): عمود واحد متوافق مع ATS. */
    $profile = $data['profile'] ?? [];
    $identity = $pulled['profile'] ?? [];
    $skills = collect(explode(',', (string) ($data['skills'] ?? '')))->map(fn ($s) => trim($s))->filter()->values();
    $line = fn (array $row) => trim(($row['from'] ?? '').' — '.(($row['current'] ?? null) ? setting('cv.until_now_label', 'حتى الآن') : ($row['to'] ?? '')), ' —');
@endphp

<div class="sheet">
    <header>
        <h1>{{ $identity['name'] ?? ($profile['job_title'] ?? setting('cv.sheet.untitled', 'سيرتي الذاتيّة')) }}</h1>
        <p class="muted">
            {{ collect([$profile['job_title'] ?? null, $profile['company'] ?? null])->filter()->implode(' · ') }}
        </p>
        <p class="muted">
            {{ collect([
                $profile['email'] ?? ($identity['email'] ?? null),
                $profile['phone'] ?? ($identity['phone'] ?? null),
                $profile['city'] ?? ($identity['governorate'] ?? null),
                $identity['country'] ?? null,
            ])->filter()->implode(' · ') }}
        </p>
    </header>

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
                @if (! empty($row['major']))<p class="muted">{{ $row['major'] }}</p>@endif
            </div>
        @endforeach
    @endif

    @if ($skills->isNotEmpty())
        <h2>{{ setting('cv.section.skills_label', 'المهارات') }}</h2>
        <div class="chips">
            @foreach ($skills as $skill)<span class="chip">{{ $skill }}</span>@endforeach
        </div>
    @endif

    @if (! empty($data['languages']))
        <h2>{{ setting('cv.section.languages_label', 'اللغات') }}</h2>
        <ul>
            @foreach ($data['languages'] as $row)
                <li>{{ $row['language'] ?? '' }}{{ ! empty($row['level']) ? ' — '.$row['level'] : '' }}</li>
            @endforeach
        </ul>
    @endif

    @if (($pulled['certificates'] ?? collect())->isNotEmpty())
        {{-- سحبٌ تلقائيّ من المنصّة (9) — والمخفيّ لا يظهر --}}
        <h2>{{ setting('cv.section.certificates_label', 'الشهادات') }}</h2>
        <ul>
            @foreach ($pulled['certificates'] as $certificate)
                <li>
                    {{ $certificate->certificate_type?->name_ar }}
                    <span class="muted">— {{ $certificate->issued_at?->translatedFormat('F Y') }} · {{ $certificate->code }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
