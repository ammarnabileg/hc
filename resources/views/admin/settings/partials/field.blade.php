@php
    /**
     * صفّ إعداد واحد بكلّ قواعد 2.13-و:
     * المفتاح ظاهر · الافتراضيّ Placeholder (مرساة) · ↺ Reset · مثال قيمة حيّ ·
     * حفظ تلقائيّ بـ«تم الحفظ ✓» جنب الحقل · Audit بالـHover بتأخير ~200ms.
     */
    $disabled = $registry->isDisabled($setting);
    $toggler = $registry->togglerOf($setting->key);
    $example = $registry->liveExample($setting);
    $requiresReason = $requiresReason ?? false;
@endphp

<div class="setting-row" data-setting="{{ $setting->key }}" data-type="{{ $setting->type }}"
     data-endpoint="{{ $endpoint }}" data-reason="{{ $requiresReason ? '1' : '0' }}"
     style="border-bottom: 1px solid var(--border); padding-bottom: .75rem">

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0">
            <div class="text-sm font-semibold flex items-center gap-2">
                {{ $setting->label_ar }}
                @if ($setting->is_owner_only)<span title="مجموعة محميّة لمالك المنصّة"><x-icon name="lock" size="16" /></span>@endif
            </div>
            {{-- إظهار مفتاح الإعداد (Key) بنمط «المجال.الميزة.المفتاح» --}}
            <code class="text-xs" style="color: var(--text-muted)">{{ $setting->key }}</code>
        </div>

        <div class="flex items-center gap-2">
            {{-- Audit بالـHover بتأخير ~200ms، وعلى اللمس بضغطة على الأيقونة --}}
            <button type="button" class="text-xs opacity-60 hover:opacity-100" data-audit-trigger aria-label="آخر تعديل">ⓘ</button>
            <button type="button" class="text-xs underline" data-setting-reset><x-icon name="refresh" size="16" /> Reset</button>
        </div>
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-2">
        @if ($setting->type === 'bool')
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" data-setting-input @checked((bool) setting($setting->key)) @disabled($disabled)>
                <span>مفعَّل</span>
            </label>
        @elseif ($setting->type === 'text' || $setting->type === 'json')
            <textarea rows="3" data-setting-input @disabled($disabled)
                      placeholder="{{ $setting->default_value }}"
                      class="w-full rounded-xl px-3 py-2 text-sm"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $setting->value }}</textarea>
        @else
            <input type="{{ $setting->type === 'number' ? 'number' : 'text' }}" data-setting-input
                   value="{{ $setting->is_sensitive ? '' : $setting->value }}"
                   placeholder="{{ $setting->is_sensitive ? '••••••' : $setting->default_value }}"
                   @disabled($disabled)
                   class="rounded-xl px-3 py-2 text-sm w-full md:w-72"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        @endif

        {{-- مؤشّر «تم الحفظ» جنب الحقل نفسه لا أعلى الصفحة (2.13-و · 2.17-ب) --}}
        <span class="text-xs" data-setting-status style="color: var(--color-state-ok)"></span>
    </div>

    @if ($requiresReason)
        <input type="text" data-setting-reason placeholder="سبب التعديل (إلزاميّ)"
               class="mt-2 rounded-xl px-3 py-2 text-xs w-full md:w-96"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
    @endif

    <div class="mt-1 text-xs" style="color: var(--text-muted)">
        @if ($disabled)
            {{-- إعدادات ميزة موقوفة تظهر معطَّلة بسطر واضح بدل تعديل بلا أثر --}}
            <span>فعّل الميزة أوّلًا: <code>{{ $toggler }}</code></span>
        @else
            <span data-setting-example>{{ $example ?? $setting->hint }}</span>
            <span class="opacity-70"> · {{ $registry->rangeHint($setting) }}</span>
        @endif
    </div>

    <div class="mt-1 text-xs hidden" data-audit-box style="color: var(--text-muted)"></div>
</div>
