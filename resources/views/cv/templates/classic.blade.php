@php
    use App\Services\Library\CvBuilder;

    /** قالب «كلاسيك» — المجّانيّ (21.2-ج): عمود واحد متوافق مع ATS. */
    $profile = $data['profile'] ?? [];
    $identity = $pulled['profile'] ?? [];
    $lang = CvBuilder::lang($data);
    $skills = collect(explode(',', (string) ($data['skills'] ?? '')))->map(fn ($s) => trim($s))->filter()->values();
    $line = fn (array $row) => trim(($row['from'] ?? '').' — '.(($row['current'] ?? null) ? setting('cv.until_now_label', 'حتى الآن') : ($row['to'] ?? '')), ' —');
    $summary = CvBuilder::text($profile, 'summary', $lang);
    // الصورة الشخصيّة: لو غابت **لا تُحسَب في العرض** — بلا Placeholder (9)
    $photo = $pulled['photo'] ?? null;
@endphp

<div class="sheet">
    {{-- ⭐ الطبقة الزخرفيّة (Drag-drop المرحلة 1 · 12.7-ب) — فوق/خلف المحتوى بلا ربط بيانات --}}
    @include('cv.templates.partials.decor-layer')

    <header @class(['with-photo' => (bool) $photo])>
        @if ($photo)
            <img class="cv-photo" src="{{ \Illuminate\Support\Facades\Storage::url($photo) }}" alt="">
        @endif

        <div>
            <h1>{{ $identity['name'] ?? (CvBuilder::text($profile, 'job_title', $lang) ?: setting('cv.sheet.untitled', 'سيرتي الذاتيّة')) }}</h1>
            <p class="muted">
                {{ collect([CvBuilder::text($profile, 'job_title', $lang) ?: null, $profile['company'] ?? null])->filter()->implode(' · ') }}
            </p>
            <p class="muted">
                {{ collect([
                    $profile['email'] ?? ($identity['email'] ?? null),
                    $profile['phone'] ?? ($identity['phone'] ?? null),
                    $profile['city'] ?? ($identity['governorate'] ?? null),
                    $identity['country'] ?? null,
                ])->filter()->implode(' · ') }}
            </p>
        </div>
    </header>

    @if ($summary !== '')
        <h2>{{ setting('cv.section.summary_label', 'نبذة مهنيّة') }}</h2>
        <p>{{ $summary }}</p>
    @endif

    @if (! empty($data['experience']))
        <h2>{{ setting('cv.section.experience_label', 'الخبرة العمليّة') }}</h2>
        @foreach ($data['experience'] as $row)
            <div class="entry">
                <div class="row">
                    <strong>{{ $row['title'] ?? '' }}{{ ! empty($row['company']) ? ' — '.$row['company'] : '' }}</strong>
                    <span class="muted">{{ $line($row) }}</span>
                </div>
                @php $note = CvBuilder::text((array) $row, 'description', $lang) @endphp
                @if ($note !== '')<p class="muted">{{ $note }}</p>@endif
            </div>
        @endforeach
    @endif

    {{-- 💖 الخبرة التطوّعيّة (9) --}}
    @if (! empty($data['volunteering']))
        <h2>{{ setting('cv.section.volunteering_label', 'الخبرة التطوّعيّة') }}</h2>
        @foreach ($data['volunteering'] as $row)
            <div class="entry">
                <div class="row">
                    <strong>{{ $row['role'] ?? '' }}{{ ! empty($row['organization']) ? ' — '.$row['organization'] : '' }}</strong>
                    <span class="muted">{{ $line($row) }}</span>
                </div>
                @php $note = CvBuilder::text((array) $row, 'description', $lang) @endphp
                @if ($note !== '')<p class="muted">{{ $note }}</p>@endif
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

    {{-- 🎓 الدورات التدريبيّة (9) --}}
    @if (! empty($data['courses']))
        <h2>{{ setting('cv.section.courses_label', 'الدورات التدريبيّة') }}</h2>
        <ul>
            @foreach ($data['courses'] as $row)
                <li>
                    {{ $row['name'] ?? '' }}
                    <span class="muted">{{ collect([$row['provider'] ?? null, $row['date'] ?? null, $row['serial'] ?? null, $row['url'] ?? null])->filter()->implode(' · ') }}</span>
                </li>
            @endforeach
        </ul>
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

    {{-- ⭐ الربط التلقائيّ: التدريبات المكتملة تُضاف تلقائيًّا (9) --}}
    @if (($pulled['trainings'] ?? collect())->isNotEmpty())
        <h2>{{ setting('cv.section.trainings_label', 'تدريبات المنصّة المكتملة') }}</h2>
        <ul>
            @foreach ($pulled['trainings'] as $enrollment)
                <li>{{ $lang === 'en' ? ($enrollment->course?->name_en ?: $enrollment->course?->name_ar) : $enrollment->course?->name_ar }}</li>
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
