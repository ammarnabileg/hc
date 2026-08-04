@php
    /**
     * بلوك إعدادات مطويّ (2.15-ب: تقسيم لا تمرير).
     * $rows: مخرجات SettingsWriter::groupRows() · $action: مسار الحفظ
     * $resetAction: مسار ↺ Reset للتاب (اختياريّ) · $lockedKeys: مفاتيح مقفولة
     * $open: يُفتَح افتراضيًّا؟ (الافتراضيّ: مطويّ — الأبسط أوّلًا 2.15-أ-10)
     */
    $lockedKeys = $lockedKeys ?? [];
    $title = $title ?? setting('admin.volunteer.partials.settings_card.aliadadat', 'الإعدادات');
    $open = $open ?? false;
    $resetAction = $resetAction ?? null;
    $resetPayload = $resetPayload ?? [];
@endphp

<details class="card p-4 md:p-5 mt-4" @if ($open) open @endif>
    <summary class="cursor-pointer font-bold select-none"><x-icon name="settings" size="16" /> {{ $title }}</summary>

    <form method="post" action="{{ $action }}" class="mt-3">
        @csrf
        @foreach ($rows as $row)
            @include('admin.volunteer.partials.setting-field', [
                'row' => $row,
                'locked' => in_array($row['key'], $lockedKeys, true),
            ])
        @endforeach

        <div class="flex items-center gap-2 mt-4">
            <button type="submit"
                    class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.partials.settings_card.ahfz', 'احفظ') }}</button>
            <span class="text-xs" style="color: var(--text-muted)">{{ setting('admin.volunteer.partials.settings_card.altadyl_ysry_fwra_ala_almnsa_klha', 'التعديل يسري فورًا على المنصّة كلّها.') }}</span>
        </div>
    </form>

    @if ($resetAction)
        <form method="post" action="{{ $resetAction }}" class="mt-3">
            @csrf
            @foreach ($resetPayload as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <button type="submit" class="text-xs underline" style="color: var(--text-muted)"><x-icon name="refresh" size="16" /> {{ setting('admin.volunteer.partials.settings_card.rja_altab_klh_llaftrady', 'رجّع التاب كلّه للافتراضيّ') }}</button>
        </form>
    @endif
</details>
