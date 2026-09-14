@php
    /**
     * محرّر قائمة CRUD عامّ (إضافة/تعديل/حذف) — نفس أسلوب «أسباب الشكاوى»
     * (12.6-ج · 11)، مُجرَّدًا هنا كي تستعمله تصنيفات دليل المستخدم ووسومه معًا
     * بلا نصٍّ مكرَّر (2.13 · DRY).
     *
     * الخصائص: $action · $fieldName (اسم حقل كلّ صفّ) · $items (القيم الحاليّة)
     * $addLabel · $newPlaceholder · $removeLabel · $saveLabel · $emptyRequired
     * (رسالة الخطأ لو القائمة فضيت وتطلّب عنصرًا واحدًا) · $defaultsHint (نصّ
     * «الافتراضيّ:») · $defaults (القيم الافتراضيّة تُعرَض كمرساة) · $maxLength
     */
    $maxLength = $maxLength ?? 64;
@endphp

<form method="post" action="{{ $action }}" class="card p-4" data-list-editor>
    @csrf
    @method('put')

    <div class="space-y-2" data-list-editor-rows>
        @foreach ($items as $item)
            <div class="flex items-center gap-2" data-list-editor-row>
                <input type="text" name="{{ $fieldName }}[]" value="{{ $item }}" maxlength="{{ $maxLength }}"
                       class="flex-1 rounded-xl px-3 text-sm"
                       style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <button type="button" data-list-editor-remove
                        class="rounded-lg px-3 text-sm"
                        style="min-height: 44px; background: var(--surface-sunken); color: var(--text-muted)"
                        aria-label="{{ $removeLabel }}">
                    <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" fill="none"
                         stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                        <path d="M3 3l8 8M11 3l-8 8" />
                    </svg>
                </button>
            </div>
        @endforeach
    </div>

    <template data-list-editor-template>
        <div class="flex items-center gap-2" data-list-editor-row>
            <input type="text" name="{{ $fieldName }}[]" value="" maxlength="{{ $maxLength }}"
                   placeholder="{{ $newPlaceholder }}"
                   class="flex-1 rounded-xl px-3 text-sm"
                   style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
            <button type="button" data-list-editor-remove class="rounded-lg px-3 text-sm"
                    style="min-height: 44px; background: var(--surface-sunken); color: var(--text-muted)"
                    aria-label="{{ $removeLabel }}">
                <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" fill="none"
                     stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                    <path d="M3 3l8 8M11 3l-8 8" />
                </svg>
            </button>
        </div>
    </template>

    @if (! empty($emptyRequired))
        @error($fieldName)
            <p class="text-xs mt-2" style="color: var(--color-state-danger)">{{ $message }}</p>
        @enderror
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2 mt-4">
        <button type="button" data-list-editor-add class="btn rounded-xl px-4 text-sm"
                style="min-height: 44px; background: var(--surface-sunken); color: var(--text)">{{ $addLabel }}</button>

        <button type="submit" class="btn rounded-xl px-5 text-sm font-semibold motion-standard"
                style="min-height: 44px; background: var(--color-brand-500); color: #04201c">{{ $saveLabel }}</button>
    </div>

    @if (! empty($defaults))
        <p class="text-xs mt-3" style="color: var(--text-muted)">{{ $defaultsHint }} {{ implode(' · ', $defaults) }}</p>
    @endif
</form>

@once
    @push('scripts')
        <script>
            /* محرّر قائمة CRUD عامّ: إضافة/حذف صفّ — بلا إعادة تحميل (2.17-ب) */
            document.querySelectorAll('[data-list-editor]').forEach((form) => {
                const list = form.querySelector('[data-list-editor-rows]');
                const tpl = form.querySelector('[data-list-editor-template]');

                form.querySelector('[data-list-editor-add]')?.addEventListener('click', () => {
                    list.appendChild(tpl.content.cloneNode(true));
                    list.lastElementChild.querySelector('input')?.focus();
                });

                list.addEventListener('click', (e) => {
                    if (!e.target.closest('[data-list-editor-remove]')) return;
                    e.target.closest('[data-list-editor-row]').remove();
                });
            });
        </script>
    @endpush
@endonce
