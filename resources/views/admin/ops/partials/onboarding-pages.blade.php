{{--
    صفحتا «تحت المراجعة» (auth/pending) و«تم قبول حسابك» (onboarding/accepted) — نفس
    الـHTML الذي يقرأه المستخدم بالضبط بـ`{!! setting(...) !!}` (12.7-أ). محرّر كود +
    معاينة حيّة لكلّ صفحة — على نمط المعاينة الحيّة المنصوص لشاشة الهويّة والمظهر.
--}}
<div class="card p-3 mb-4 text-xs" style="color: var(--text-muted)">
    {{ setting('admin.ops.partials.onboarding_pages.hint', 'الكود اللي بتكتبه هنا بيظهر بالحرف في الصفحة اللي بيشوفها المستخدم — والمعاينة تحت كلّ محرّر مطابقة له فورًا.') }}
</div>

<form method="post" action="{{ route('admin.ops.onboarding.pages') }}" class="space-y-4" id="onboarding-pages-form">
    @csrf

    <div class="card p-4">
        <h2 class="text-sm font-bold mb-1">{{ setting('admin.ops.partials.onboarding_pages.review_title', 'صفحة «تحت المراجعة»') }}</h2>
        <p class="text-xs mb-3" style="color: var(--text-muted)">{{ setting('admin.ops.partials.onboarding_pages.review_hint', 'يشوفها المستخدم بعد التسجيل مباشرة وقبل ما يوافق الأدمن على حسابه.') }}</p>

        <div class="grid gap-3 md:grid-cols-2">
            <label class="block min-w-0">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.ops.partials.onboarding_pages.kod_html', 'كود HTML') }}</span>
                <textarea name="review_html" id="onboarding-review-html" rows="8" dir="ltr"
                          data-preview-source="onboarding-review-preview"
                          @cannot('onboarding.edit') disabled @endcannot
                          class="w-full rounded-xl px-3 py-2 text-sm font-mono"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('review_html', $pages['review_html'] ?? '') }}</textarea>
            </label>

            <div class="block min-w-0">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.ops.partials.onboarding_pages.almaayna_alhya', 'المعاينة الحيّة') }}</span>
                <div id="onboarding-review-preview" class="rounded-xl p-3 text-sm overflow-auto"
                     style="background: var(--surface-sunken); border: 1px dashed var(--border); color: var(--text-muted); min-height: 9.5rem">{!! $pages['review_html'] ?? '' !!}</div>
            </div>
        </div>
    </div>

    <div class="card p-4">
        <h2 class="text-sm font-bold mb-1">{{ setting('admin.ops.partials.onboarding_pages.accepted_title', 'صفحة «تم قبول حسابك»') }}</h2>
        <p class="text-xs mb-3" style="color: var(--text-muted)">{{ setting('admin.ops.partials.onboarding_pages.accepted_hint', 'يشوفها المستخدم أوّل ما يُقبَل حسابه، قبل ما يدخل المنصّة.') }}</p>

        <div class="grid gap-3 md:grid-cols-2">
            <label class="block min-w-0">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.ops.partials.onboarding_pages.kod_html', 'كود HTML') }}</span>
                <textarea name="accepted_html" id="onboarding-accepted-html" rows="8" dir="ltr"
                          data-preview-source="onboarding-accepted-preview"
                          @cannot('onboarding.edit') disabled @endcannot
                          class="w-full rounded-xl px-3 py-2 text-sm font-mono"
                          style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ old('accepted_html', $pages['accepted_html'] ?? '') }}</textarea>
            </label>

            <div class="block min-w-0">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">{{ setting('admin.ops.partials.onboarding_pages.almaayna_alhya', 'المعاينة الحيّة') }}</span>
                <div id="onboarding-accepted-preview" class="rounded-xl p-3 text-sm overflow-auto"
                     style="background: var(--surface-sunken); border: 1px dashed var(--border); color: var(--text-muted); min-height: 9.5rem">{!! $pages['accepted_html'] ?? '' !!}</div>
            </div>
        </div>
    </div>

    @can('onboarding.edit')
        <button class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.ops.partials.onboarding_pages.hfz', 'حفظ') }}</button>
    @endcan
</form>

@push('scripts')
    <script>
        // معاينة حيّة بلا Round-trip — كتابة في المحرّر تنعكس فورًا في الصندوق المجاور (2.15-أ)
        (function () {
            document.querySelectorAll('#onboarding-pages-form textarea[data-preview-source]').forEach((editor) => {
                const preview = document.getElementById(editor.dataset.previewSource);
                if (!preview) return;

                editor.addEventListener('input', () => {
                    preview.innerHTML = editor.value;
                });
            });
        })();
    </script>
@endpush
