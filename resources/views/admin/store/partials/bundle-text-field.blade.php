{{--
    ⭐ **حقل نصٍّ موروث** — قلبُ أمر المالك: «أيّ نصوص … قابل للتعديل من إعدادات
    نفس البندل».

    وثلاثة تفاصيل ليست زينة:
      · **الـPlaceholder هو القيمة العامّة** — فيرى الأدمن ما سيرثه قبل أن يقرّر.
      · **شارة «موروث»/«مخصّص»** — بلا الشارة يستحيل معرفة أنّ حقلًا فارغًا يعمل.
      · **↺ رجّع للموروث يمسح** ولا يكتب الافتراضيّ — لأنّ الكتابة تجمّد البندل
        على قيمة اليوم فيصير تعديلُ النصّ العامّ بلا أثر (نقضُ 2.13 من داخلها).

    المتغيّرات: $bundle · $landingService · $key · $label · $rows (اختياريّ)
--}}
@php
    $overridden = $landingService->isOverridden($bundle, $key);
    $globalValue = $landingService->globalText($key);
    $ownValue = ($bundle->landing_texts ?? [])[$key] ?? '';
    $rows ??= 0;
@endphp

<label class="block text-sm min-w-0">
    <span class="flex items-center gap-2 mb-1 flex-wrap">
        <span class="font-semibold">{{ $label }}</span>

        @if ($overridden)
            <span class="text-xs rounded-full px-2 py-0.5"
                  style="background: color-mix(in srgb, var(--color-state-honor) 18%, transparent); color: var(--color-state-honor)">{{ setting('store.admin.bundles.custom_badge') }}</span>
            {{-- المسح يقع بإرسال الحقل فارغًا — والفارغ لا يُخزَّن (راجع `compactMap`) --}}
            <button type="button" data-revert-field="{{ $key }}"
                    class="text-xs hover:underline" style="color: var(--text-muted)">{{ setting('store.admin.bundles.revert_label') }}</button>
        @else
            <span class="text-xs rounded-full px-2 py-0.5"
                  style="background: var(--surface-sunken); color: var(--text-muted)">{{ setting('store.admin.bundles.inherited_badge') }}</span>
        @endif
    </span>

    @if ($rows > 0)
        <textarea name="landing_texts[{{ $key }}]" rows="{{ $rows }}" placeholder="{{ $globalValue }}"
                  data-landing-text="{{ $key }}"
                  class="w-full rounded-xl px-3 py-2 text-sm"
                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical">{{ $ownValue }}</textarea>
    @else
        <input type="text" name="landing_texts[{{ $key }}]" value="{{ $ownValue }}" placeholder="{{ $globalValue }}"
               data-landing-text="{{ $key }}" maxlength="2000"
               class="w-full rounded-xl px-3 py-2 text-sm"
               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); min-height: 44px">
    @endif

    <span class="block text-xs mt-1" style="color: var(--text-muted)"><code>{{ $key }}</code></span>
</label>
