@php $p = $data['profile'] ?? []; @endphp

<div class="card p-4" data-step-panel="profile">
    <form data-step-form="profile" onsubmit="return false" class="grid gap-3 sm:grid-cols-2">
        <label class="block">
            <span class="block text-sm mb-1">{{ setting('cv.field.job_title_label', 'المسمّى الوظيفيّ الحاليّ') }}</span>
            <input type="text" name="data[profile][job_title]" value="{{ $p['job_title'] ?? '' }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('cv.field.company_label', 'الشركة / الجهة') }}</span>
            <input type="text" name="data[profile][company]" value="{{ $p['company'] ?? '' }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('cv.field.years_label', 'سنوات الخبرة') }}</span>
            <select name="data[profile][years]" class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">—</option>
                @foreach ((array) setting('cv.options.years', ['أقلّ من سنة', '1–3 سنوات', '3–5 سنوات', '5–10 سنوات', 'أكثر من 10 سنوات']) as $option)
                    <option value="{{ $option }}" @selected(($p['years'] ?? '') === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('cv.field.stage_label', 'المرحلة المهنيّة') }}</span>
            <select name="data[profile][stage]" class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">—</option>
                @foreach ((array) setting('cv.options.stages', ['طالب', 'مبتدئ', 'متوسّط', 'خبير', 'قياديّ']) as $option)
                    <option value="{{ $option }}" @selected(($p['stage'] ?? '') === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('cv.field.major_label', 'التخصّص الأكاديميّ') }}</span>
            <input type="text" name="data[profile][major]" value="{{ $p['major'] ?? '' }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('cv.field.native_language_label', 'اللغة الأمّ') }}</span>
            <select name="data[profile][native_language]" class="w-full rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">—</option>
                @foreach ((array) setting('cv.options.native_languages', ['العربيّة', 'الإنجليزيّة', 'الفرنسيّة']) as $option)
                    <option value="{{ $option }}" @selected(($p['native_language'] ?? '') === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('cv.field.email_label', 'البريد') }}</span>
            <input type="email" name="data[profile][email]" value="{{ $p['email'] ?? '' }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="block">
            <span class="block text-sm mb-1">{{ setting('cv.field.phone_label', 'الهاتف') }}</span>
            <input type="text" name="data[profile][phone]" value="{{ $p['phone'] ?? '' }}"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="block sm:col-span-2">
            <span class="block text-sm mb-1">{{ setting('cv.field.summary_label', 'ملخّص مهنيّ (اختياريّ)') }}</span>
            <textarea name="data[profile][summary]" rows="3" class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $p['summary'] ?? '' }}</textarea>
            <span class="block text-xs mt-1" style="color: var(--text-muted)">{{ setting('cv.field.summary_hint', 'سطران عن مسيرتك وطموحك.') }}</span>
        </label>

        {{-- ثنائيّة AR/EN بصفر تكلفة (9): حقلا لغةٍ ثانية اختياريّان، ولو فُرِّغا ظهر الأصل --}}
        <label class="block" data-lang-en hidden>
            <span class="block text-sm mb-1">{{ setting('cv.field.job_title_en_label', 'Job title (English) — optional') }}</span>
            <input type="text" name="data[profile][job_title_en]" value="{{ $p['job_title_en'] ?? '' }}" dir="ltr"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <label class="block sm:col-span-2" data-lang-en hidden>
            <span class="block text-sm mb-1">{{ setting('cv.field.summary_en_label', 'Professional summary (English) — optional') }}</span>
            <textarea name="data[profile][summary_en]" rows="3" dir="ltr" class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $p['summary_en'] ?? '' }}</textarea>
        </label>
    </form>
</div>
