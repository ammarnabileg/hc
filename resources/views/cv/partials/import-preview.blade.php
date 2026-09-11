@php
    /*
     | ⭐ معاينةٌ تحريريّة حقيقيّة قبل الحفظ (9) — لا عدّاداتٌ فقط: كلّ صفٍّ
     | مستخرَج يظهر بنفس جزء صفّ بنّاء السيرة (`row-experience.blade.php` إلخ)
     | معبّأً بالمستخرَج، فلا تتكرّر واجهة إدخال الخبرة/التعليم مرّتين في المشروع.
     |
     | ⚠️ `CvImporter::parse()` لا يستخرج دوراتٍ ولا خبرةً تطوّعيّة إطلاقًا
     | (حقلا `courses`/`volunteering` غائبان عن مخرَجه أصلًا) — فلا قسمَين
     | لهما هنا: لا دورًا وهميًّا فارغًا يوحي باستخراجٍ لم يحدث.
     */
    $field = 'background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)';
    $profile = (array) ($parsed['profile'] ?? []);
    $skills = trim((string) ($parsed['skills'] ?? ''));
@endphp

<div class="space-y-4" data-import-fields>
    <div data-import-section="profile">
        <h3 class="text-xs font-bold mb-2" style="color: var(--text-muted)">{{ setting('cv.import.profile_label', 'البيانات الأساسيّة') }}</h3>
        <div class="grid gap-2 sm:grid-cols-2" data-import-profile>
            <input type="text" data-field="job_title" value="{{ $profile['job_title'] ?? '' }}"
                   placeholder="{{ setting('cv.field.job_title_label', 'المسمّى الوظيفيّ') }}"
                   class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

            <input type="text" data-field="company" value="{{ $profile['company'] ?? '' }}"
                   placeholder="{{ setting('cv.field.company_label', 'الشركة') }}"
                   class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

            <input type="email" data-field="email" value="{{ $profile['email'] ?? '' }}" dir="ltr"
                   placeholder="{{ setting('cv.field.email_label', 'البريد') }}"
                   class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

            <input type="text" data-field="phone" value="{{ $profile['phone'] ?? '' }}" dir="ltr"
                   placeholder="{{ setting('cv.field.phone_label', 'الهاتف') }}"
                   class="rounded-xl px-3 py-2 text-sm" style="{{ $field }}">

            <textarea data-field="summary" rows="2"
                      placeholder="{{ setting('cv.field.summary_label', 'ملخّص مهنيّ (اختياريّ)') }}"
                      class="sm:col-span-2 rounded-xl px-3 py-2 text-sm" style="{{ $field }}">{{ $profile['summary'] ?? '' }}</textarea>
        </div>
    </div>

    @if (! empty($parsed['experience']))
        <div data-import-section="experience">
            <h3 class="text-xs font-bold mb-2" style="color: var(--text-muted)">
                {{ setting('cv.step.experience_label', 'الخبرات') }} ({{ count($parsed['experience']) }})
            </h3>
            <div class="space-y-2" data-import-list="experience">
                @foreach ($parsed['experience'] as $i => $row)
                    @include('cv.partials.row-experience', ['i' => $i, 'row' => $row, 'field' => $field])
                @endforeach
            </div>
        </div>
    @endif

    @if (! empty($parsed['education']))
        <div data-import-section="education">
            <h3 class="text-xs font-bold mb-2" style="color: var(--text-muted)">
                {{ setting('cv.step.education_label', 'التعليم') }} ({{ count($parsed['education']) }})
            </h3>
            <div class="space-y-2" data-import-list="education">
                @foreach ($parsed['education'] as $i => $row)
                    @include('cv.partials.row-education', ['i' => $i, 'row' => $row, 'field' => $field])
                @endforeach
            </div>
        </div>
    @endif

    @if (! empty($parsed['languages']))
        <div data-import-section="languages">
            <h3 class="text-xs font-bold mb-2" style="color: var(--text-muted)">
                {{ setting('cv.field.languages_label', 'اللغات') }} ({{ count($parsed['languages']) }})
            </h3>
            <div class="space-y-2" data-import-list="languages">
                @foreach ($parsed['languages'] as $i => $row)
                    @include('cv.partials.row-language', ['i' => $i, 'row' => $row, 'field' => $field])
                @endforeach
            </div>
        </div>
    @endif

    @if ($skills !== '')
        <div data-import-section="skills">
            <h3 class="text-xs font-bold mb-2" style="color: var(--text-muted)">{{ setting('cv.field.skills_label', 'أضف مهاراتك (افصل بفاصلة)') }}</h3>
            <textarea data-import-skills rows="2" class="w-full rounded-xl px-3 py-2 text-sm" style="{{ $field }}">{{ $skills }}</textarea>
        </div>
    @endif
</div>
