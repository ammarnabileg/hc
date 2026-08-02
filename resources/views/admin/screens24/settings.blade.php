@php
    /**
     * بلوك إعدادات موحّد للشاشات الأربع (2.13-أ/و).
     *
     * مطويّ لأنّ الأبسط أوّلًا (2.15-أ-10)، ولكلّ حقل **مفتاحه ظاهر** وشرحه
     * القصير وافتراضيّه كمرساة وشارة «معدَّل» — وReset لكلّ الصفحة.
     *
     * المتغيّرات: $settings · $saveRoute · $resetRoute · $blockTitle
     */
    $blockTitle ??= 'إعدادات الشاشة';
@endphp

@if (! empty($settings))
    <details class="card p-4 md:p-5 mt-4">
        <summary class="cursor-pointer font-bold select-none"><x-icon name="settings" size="16" /> {{ $blockTitle }}</summary>

        <p class="text-xs mt-2" style="color: var(--text-muted)">
            كلّ رقم ونصّ في الشاشة دي بيتظبط من هنا — مافيش قيمة محروقة في الكود (2.13).
        </p>

        <form method="post" action="{{ $saveRoute }}" class="mt-3">
            @csrf

            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($settings as $row)
                    <label class="block {{ in_array($row['type'], ['lines', 'text'], true) ? 'md:col-span-2' : '' }}">
                        <span class="block text-sm font-semibold mb-1">
                            {{ $row['label'] }}
                            @if ($row['owner_only'])
                                <span class="text-xs" style="color: var(--color-state-honor)"><x-icon name="lock" size="16" /> مالك المنصّة</span>
                            @endif
                        </span>

                        @if ($row['type'] === 'lines' || $row['type'] === 'text')
                            <textarea name="settings[{{ $row['key'] }}]" rows="{{ $row['type'] === 'lines' ? 5 : 3 }}"
                                      class="w-full rounded-xl px-3 py-2 text-sm"
                                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text); resize: vertical">{{ $row['value'] }}</textarea>
                        @elseif ($row['type'] === 'bool')
                            <span class="flex items-center gap-2">
                                <input type="hidden" name="settings[{{ $row['key'] }}]" value="0">
                                <input type="checkbox" name="settings[{{ $row['key'] }}]" value="1" @checked($row['value'] === '1')>
                                <span class="text-sm">مفعّل</span>
                            </span>
                        @elseif ($row['type'] === 'number')
                            <input type="number" name="settings[{{ $row['key'] }}]" min="0" max="1000000"
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
                            <code>{{ $row['key'] }}</code>
                            @if ($row['hint']) · {{ $row['hint'] }} @endif
                            @if ($row['type'] !== 'bool') · الافتراضيّ: {{ $row['default'] }} @endif
                            @if ($row['modified'])
                                · <span style="color: var(--color-state-warn)">▲ معدَّل</span>
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

        <form method="post" action="{{ $resetRoute }}" class="mt-3"
              onsubmit="return confirm('ترجّع كلّ إعدادات الشاشة دي للافتراضيّ؟')">
            @csrf
            <button type="submit" class="text-xs underline" style="color: var(--text-muted)"><x-icon name="refresh" size="16" /> رجّع الإعدادات كلّها للافتراضيّ</button>
        </form>
    </details>
@endif
