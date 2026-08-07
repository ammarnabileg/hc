@php
    /**
     * صفّ إعداد واحد لشاشة إعدادات التعلّم (24.4): المفتاح ظاهر · الافتراضيّ
     * Placeholder · ↺ Reset · حفظ تلقائيّ بـ«تم الحفظ ✓» جنب الحقل (2.13-و).
     *
     * ⭐ صفّ مستقلّ عن `admin.settings.partials.field` عمدًا: ذاك يربط زرّ الـReset
     * بمسار `admin.settings.reset` المحروس بصلاحيّة `settings_general.edit` —
     * ومَن يملك `learning_ux.edit` هنا لا يملكها بالضرورة (12.2.2)، فيفشل
     * الزرّ بـ403 صامتًا. هذا الصفّ يربط بمسارات الشاشة نفسها فقط.
     */
    $disabled = ! $canEdit || $registry->isDisabled($setting);
@endphp

<div class="learning-setting-row" data-setting="{{ $setting->key }}" data-type="{{ $setting->type }}"
     style="border-bottom: 1px solid var(--border); padding-bottom: .75rem">

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0">
            <div class="text-sm font-semibold">{{ $setting->label_ar }}</div>
            <code class="text-xs" style="color: var(--text-muted); overflow-wrap: anywhere">{{ $setting->key }}</code>
        </div>

        @if ($canEdit)
            <button type="button" class="text-xs underline" data-learning-setting-reset>
                <x-icon name="refresh" size="16" /> {{ setting('admin.content.learning_settings.field.reset', 'Reset') }}
            </button>
        @endif
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-2">
        @if ($setting->type === 'bool')
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" data-learning-setting-input @checked((bool) setting($setting->key)) @disabled($disabled)>
                <span>{{ setting('admin.content.learning_settings.field.enabled', 'مفعَّل') }}</span>
            </label>
        @else
            <input type="{{ $setting->type === 'number' ? 'number' : 'text' }}" data-learning-setting-input
                   value="{{ $setting->value }}"
                   placeholder="{{ $setting->default_value }}"
                   @disabled($disabled)
                   class="rounded-xl px-3 py-2 text-sm w-full md:w-72"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        @endif

        <span class="text-xs" data-learning-setting-status style="color: var(--color-state-ok)"></span>
    </div>

    <div class="mt-1 text-xs" style="color: var(--text-muted)">
        @if ($disabled && $registry->isDisabled($setting))
            <span>{{ setting('admin.content.learning_settings.field.enable_first', 'فعّل الميزة أوّلًا:') }} <code>{{ $registry->togglerOf($setting->key) }}</code></span>
        @else
            <span>{{ $setting->hint }}</span>
            @if ($range = $registry->rangeHint($setting))
                <span class="opacity-70"> · {{ $range }}</span>
            @endif
        @endif
    </div>
</div>
