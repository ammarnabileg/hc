@php
    /**
     * إعدادات الميزة (2.13-أ/و): تفعيل/إيقاف · نسب · نصوص ظاهرة —
     * ولكلّ حقل **سطر شرح قصير** و**افتراضيّه ظاهر كمرساة**، والبلوك مطويّ
     * لأنّ الأبسط أوّلًا (2.15-أ-10).
     */
@endphp

<details class="card p-4 md:p-5 mt-4">
    <summary class="cursor-pointer font-bold select-none"><x-icon name="settings" size="16" /> إعدادات الرسائل الإيجابيّة</summary>

    <p class="text-xs mt-2" style="color: var(--text-muted)">
        النِّسَب دي احتمالات حقيقيّة تُسحَب لحظة تحميل الصفحة — بلا أرقام وهميّة ولا ضغط على المستخدم (2.9).
    </p>

    <form method="post" action="{{ route('admin.positive.settings') }}" class="mt-3">
        @csrf

        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($settings as $row)
                <label class="block {{ $row['type'] === 'lines' ? 'md:col-span-2' : '' }}">
                    <span class="block text-sm font-semibold mb-1">{{ $row['label'] }}</span>

                    @if ($row['type'] === 'lines')
                        <textarea name="settings[{{ $row['key'] }}]" rows="6"
                                  class="w-full rounded-xl px-3 py-2 text-sm font-mono"
                                  style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical">{{ $row['value'] }}</textarea>
                    @elseif ($row['type'] === 'bool')
                        <span class="flex items-center gap-2">
                            <input type="hidden" name="settings[{{ $row['key'] }}]" value="0">
                            <input type="checkbox" name="settings[{{ $row['key'] }}]" value="1"
                                   @checked($row['value'] === '1')>
                            <span class="text-sm">مفعّل</span>
                        </span>
                    @elseif ($row['type'] === 'number')
                        <input type="number" name="settings[{{ $row['key'] }}]" min="0" max="100000"
                               value="{{ $row['value'] }}" placeholder="{{ $row['default'] }}"
                               class="w-full rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @else
                        <input type="text" name="settings[{{ $row['key'] }}]" maxlength="255"
                               value="{{ $row['value'] }}" placeholder="{{ $row['default'] }}"
                               class="w-full rounded-xl px-3 py-2 text-sm"
                               style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    @endif

                    <span class="block text-xs mt-1" style="color: var(--text-muted)">
                        {{ $row['hint'] ?: 'الافتراضيّ: '.$row['default'] }}
                        @if ($row['modified'])
                            · <span style="color: var(--color-state-warn)">معدَّل</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>

        <div class="flex items-center gap-2 mt-4">
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
                    style="background: var(--color-brand-500); color: #04201c">احفظ</button>
            <span class="text-xs" style="color: var(--text-muted)">التعديل يسري فورًا بلا إعادة نشر.</span>
        </div>
    </form>

    <form method="post" action="{{ route('admin.positive.settings.reset') }}" class="mt-3">
        @csrf
        <button type="submit" class="text-xs underline" style="color: var(--text-muted)"><x-icon name="refresh" size="16" /> رجّع الإعدادات كلّها للافتراضيّ</button>
    </form>
</details>
