@php
    /**
     * حقل إعداد واحد (2.13): يعرض القيمة الحاليّة، وشارة «معدَّل» إن غادر
     * افتراضيّه، ومعه سطر الافتراضيّ ليعرف المسؤول ماذا يُرجِع إليه.
     * $row: ['key','label','type','default','value','modified']
     * $locked: حقل مقفول معلَن — يُعرَض ولا يُعدَّل (قرار دستوريّ لا خيار إداريّ)
     */
    $locked = $locked ?? false;
    $id = 'set-'.str_replace(['.', '_'], '-', $row['key']);
@endphp

<div class="py-3" style="border-bottom: 1px solid var(--border)">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <label for="{{ $id }}" class="text-sm font-semibold">
            {{ $row['label'] }}
            @if ($row['modified'])
                <span class="text-xs rounded-full px-2 py-0.5 align-middle"
                      style="background: color-mix(in srgb, var(--color-state-warn) 15%, transparent); color: var(--color-state-warn)">▲ معدَّل</span>
            @endif
            @if ($locked)
                <span class="text-xs rounded-full px-2 py-0.5 align-middle"
                      style="background: var(--surface-sunken); color: var(--text-muted)"><x-icon name="lock" size="16" /> مقفول</span>
            @endif
        </label>

        <code class="text-xs" style="color: var(--text-muted)">{{ $row['key'] }}</code>
    </div>

    <div class="mt-2">
        @if ($row['type'] === 'bool')
            <select id="{{ $id }}" name="settings[{{ $row['key'] }}]" @disabled($locked)
                    class="w-full md:w-56 rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="1" @selected((string) $row['value'] === '1')>مفعَّل</option>
                <option value="0" @selected((string) $row['value'] !== '1')>موقوف</option>
            </select>
        @elseif ($row['type'] === 'json' || $row['type'] === 'text')
            <textarea id="{{ $id }}" name="settings[{{ $row['key'] }}]" rows="3" @disabled($locked)
                      class="w-full rounded-xl px-3 py-2 text-sm font-mono"
                      style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">{{ $row['value'] }}</textarea>
        @else
            <input id="{{ $id }}" type="{{ $row['type'] === 'number' ? 'number' : 'text' }}" step="any"
                   name="settings[{{ $row['key'] }}]" value="{{ $row['value'] }}" @disabled($locked)
                   class="w-full md:w-72 rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        @endif
    </div>

    {{-- القيمة الافتراضيّة قد تكون JSON بلا مسافةٍ واحدة، فلا يجد المتصفّح
         موضعًا يكسر عنده السطر ويمدّ الصفحة. `anywhere` تكسر داخل الكلمة نفسها --}}
    <div class="mt-1 text-xs" style="color: var(--text-muted); overflow-wrap: anywhere">الافتراضيّ: {{ \Illuminate\Support\Str::limit($row['default'], 90) }}</div>
</div>
