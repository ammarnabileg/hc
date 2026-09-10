@php
    use App\Services\Library\CvBuilder;

    /*
     | «خبراتي» = الـCV معروضًا بشكل احترافيّ (القسم 9 · 10.0-أ).
     |
     | ⚠️ هذه الواجهة تقرأ **من مخزن السيرة حرفيًّا**: النبذة في
     | `data.profile.summary`، والخبرة في `data.experience` (لا `experiences`)،
     | والمهارات **نصٌّ** مفصول بفواصل (لا مصفوفة). أيّ مفتاح مخترَع هنا
     | يُسقِط القسم كلَّه **بصمت** — بلا خطأ ولا أثر، والمشاهد يظنّ السيرة فارغة.
     */
    $canSee = $visibility->canSee('experience', $viewer, $owner, $level);
    $data = $experience['data'];
    $profile = (array) ($data['profile'] ?? []);
    $pulled = $experience['pulled'] ?? ['certificates' => collect(), 'trainings' => collect()];
    $lang = CvBuilder::lang($data);

    $summary = CvBuilder::text($profile, 'summary', $lang);
    $headline = collect([
        CvBuilder::text($profile, 'job_title', $lang),
        $profile['company'] ?? null,
    ])->filter()->implode(' · ');

    $skills = collect(explode(',', (string) ($data['skills'] ?? '')))
        ->map(fn ($s) => trim($s))->filter()->values();

    $period = fn (array $row) => trim(implode(' – ', array_filter([
        (string) ($row['from'] ?? ''),
        ! empty($row['current'])
            ? (string) setting('cv.until_now_label', 'حتى الآن')
            : (string) ($row['to'] ?? ''),
    ])), ' –');
@endphp

@if (! $canSee)
    <x-empty :message="setting('account.profile.experience.hidden_message', 'الخبرات مش متاحة على البروفايل ده.')" />
@elseif (! $experience['cv'])
    <x-empty :message="setting('account.profile.experience.empty_message', 'لسّه مفيش سيرة ذاتيّة هنا.')"
             :action="$isOwner ? setting('account.profile.experience.empty_action', 'ابدأ سيرتك') : null"
             :href="$isOwner && \Illuminate\Support\Facades\Route::has('cv.index') ? route('cv.index') : null" />
@else
    <div class="space-y-3">
        @if ($headline !== '')
            <section class="card p-4">
                <h2 class="font-bold">{{ $headline }}</h2>
            </section>
        @endif

        @if ($summary !== '')
            <section class="card p-4">
                <h2 class="font-bold text-sm mb-2">{{ setting('cv.section.summary_label', 'نبذة مهنيّة') }}</h2>
                <p class="text-sm leading-7">{{ $summary }}</p>
            </section>
        @endif

        {{-- الخبرة العمليّة والتطوّعيّة: صفوفٌ بمسمّى وجهة وفترة ووصف (9) --}}
        @foreach ([
            ['key' => 'experience', 'label' => setting('cv.section.experience_label', 'الخبرة العمليّة'), 'first' => 'title', 'second' => 'company'],
            ['key' => 'volunteering', 'label' => setting('cv.section.volunteering_label', 'الخبرة التطوّعيّة'), 'first' => 'role', 'second' => 'organization'],
            ['key' => 'education', 'label' => setting('cv.section.education_label', 'رحلة التعلّم'), 'first' => 'degree', 'second' => 'institution'],
        ] as $section)
            @if (! empty($data[$section['key']]) && is_array($data[$section['key']]))
                <section class="card p-4">
                    <h2 class="font-bold text-sm mb-2">{{ $section['label'] }}</h2>
                    <div class="space-y-3">
                        @foreach ($data[$section['key']] as $row)
                            @php $row = (array) $row; @endphp
                            <div>
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <strong class="text-sm">{{ collect([$row[$section['first']] ?? null, $row[$section['second']] ?? null])->filter()->implode(' — ') }}</strong>
                                    <span class="text-xs" style="color: var(--text-muted)">{{ $period($row) }}</span>
                                </div>
                                @php $note = CvBuilder::text($row, 'description', $lang) ?: (string) ($row['major'] ?? '') @endphp
                                @if (trim($note) !== '')
                                    <p class="text-sm leading-7 mt-1" style="color: var(--text-muted)">{{ $note }}</p>
                                @endif
                                @if (! empty($row['city']))
                                    <p class="text-xs mt-1" style="color: var(--text-muted)">{{ $row['city'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        {{-- 🎓 الدورات التدريبيّة: اسم · جهة · تاريخ · رقم · رابط (9) --}}
        @if (! empty($data['courses']) && is_array($data['courses']))
            <section class="card p-4">
                <h2 class="font-bold text-sm mb-2">{{ setting('cv.section.courses_label', 'الدورات التدريبيّة') }}</h2>
                <ul class="space-y-2 text-sm">
                    @foreach ($data['courses'] as $row)
                        @php $row = (array) $row; @endphp
                        <li>
                            <strong>{{ $row['name'] ?? '' }}</strong>
                            <span style="color: var(--text-muted)">{{ collect([$row['provider'] ?? null, $row['date'] ?? null, $row['serial'] ?? null])->filter()->implode(' · ') }}</span>
                            @if (! empty($row['url']))
                                <a href="{{ $row['url'] }}" target="_blank" rel="noopener nofollow"
                                   class="underline text-xs" style="color: var(--color-brand-500)">{{ setting('cv.field.certificate_url_label', 'رابط الشهادة') }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- ⚠️ المهارات **نصٌّ** مفصول بفواصل في المخزن — لا مصفوفة (9) --}}
        @if ($skills->isNotEmpty())
            <section class="card p-4">
                <h2 class="font-bold text-sm mb-2">{{ setting('cv.section.skills_label', 'المهارات') }}</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($skills as $skill)
                        <span class="rounded-full px-3 py-1 text-xs"
                              style="background: var(--surface-sunken); color: var(--text)">{{ $skill }}</span>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! empty($data['languages']) && is_array($data['languages']))
            <section class="card p-4">
                <h2 class="font-bold text-sm mb-2">{{ setting('cv.section.languages_label', 'اللغات') }}</h2>
                <ul class="space-y-1 text-sm">
                    @foreach ($data['languages'] as $row)
                        @php $row = (array) $row; @endphp
                        <li class="flex items-start gap-2">
                            <span aria-hidden="true" style="color: var(--color-brand-500)">•</span>
                            <span>{{ collect([$row['language'] ?? null, $row['level'] ?? null])->filter()->implode(' — ') }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- ⭐ الربط التلقائيّ بالمنصّة: التدريبات المكتملة تُضاف تلقائيًّا (9) --}}
        @if (($pulled['trainings'] ?? collect())->isNotEmpty())
            <section class="card p-4">
                <h2 class="font-bold text-sm mb-2">{{ setting('cv.section.trainings_label', 'تدريبات المنصّة المكتملة') }}</h2>
                <ul class="space-y-1 text-sm">
                    @foreach ($pulled['trainings'] as $enrollment)
                        <li class="flex items-start gap-2">
                            <span aria-hidden="true" style="color: var(--color-brand-500)">•</span>
                            <span>{{ $lang === 'en' ? ($enrollment->course?->name_en ?: $enrollment->course?->name_ar) : $enrollment->course?->name_ar }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{--
          ⭐ الإفادة (9.1): «خبراتي = الـCV + الإفادة إن وُجدت» (10.0-أ، قاعدة
          نهائيّة ✅) — بنفس حراسة الرؤية أعلاه ($canSee)، وتظهر فقط لو صدرت
          فعلًا (موافَق عليها/منشورة)، والرابط العامّ فقط لو فعّل صاحبها النشر.
        --}}
        @if ($experience['attestation'])
            <section class="card p-4">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h2 class="font-bold text-sm">{{ setting('account.profile.experience.attestation_title', 'الإفادة من المنصّة') }}</h2>
                    <x-state-badge :state="$experience['attestation_meta']['state']" :label="$experience['attestation_meta']['label']" />
                </div>

                @if ($experience['attestation']->body)
                    <p class="text-sm leading-7" style="color: var(--text-muted)">{{ $experience['attestation']->body }}</p>
                @endif

                @if ($experience['attestation_public_url'])
                    <a href="{{ $experience['attestation_public_url'] }}" target="_blank" rel="noopener"
                       class="underline text-xs mt-2 inline-block"
                       style="color: var(--color-brand-500)">{{ setting('account.profile.experience.attestation_link_label', 'شوف الإفادة العامّة') }}</a>
                @endif
            </section>
        @endif

        <p class="text-xs" style="color: var(--text-muted)">
            {{ setting('cv.completion.label', 'اكتمال السيرة') }} {{ $experience['cv']->completion_percent }}%
        </p>
    </div>
@endif
