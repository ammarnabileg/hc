{{--
  بوب-أب المشاركة (10): أزرار المشاركة (تيليجرام / X / فيسبوك / واتساب)
  + **الرابط العامّ للحساب**. والأيقونات SVG مرسومة داخل المشروع (2.16-ج).
--}}
<x-modal id="profile-share" title="مشاركة الحساب">
    <div class="space-y-4 text-sm">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            @foreach ($shareLinks as $link)
                <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                   class="flex flex-col items-center justify-center gap-1 rounded-xl px-3 py-3 motion-standard"
                   style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                    <span aria-hidden="true">
                        @switch($link['key'])
                            @case('telegram')
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.6" stroke-linejoin="round" focusable="false">
                                    <path d="M21 4L3 11l5 2 2 6 3-4 5 4z" />
                                    <path d="M8 13l13-9-9 11" />
                                </svg>
                                @break
                            @case('x')
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.6" stroke-linecap="round" focusable="false">
                                    <path d="M4 4l16 16M20 4L4 20" />
                                </svg>
                                @break
                            @case('facebook')
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.6" stroke-linejoin="round" focusable="false">
                                    <rect x="3" y="3" width="18" height="18" rx="4" />
                                    <path d="M14 8h-1.5A1.5 1.5 0 0011 9.5V12H9v2.5h2V21h2.5v-6.5H16L16.5 12H13.5v-1.75c0-.4.3-.75.75-.75H16V8z" />
                                </svg>
                                @break
                            @default
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                    <path d="M20 12a8 8 0 10-3.2 6.4L20 20l-1.4-3.4A7.9 7.9 0 0020 12z" />
                                    <path d="M9 9.5c0 3 2.5 5.5 5.5 5.5" />
                                </svg>
                        @endswitch
                    </span>
                    <span class="text-xs">{{ $link['label'] }}</span>
                </a>
            @endforeach
        </div>

        <label class="block">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">رابط الحساب العامّ</span>
            <div class="flex gap-2">
                <input type="text" readonly value="{{ $profileUrl }}" data-share-link
                       class="flex-1 rounded-xl px-3 text-sm font-mono"
                       style="min-height: 44px; background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <button type="button" data-copy-profile="{{ $profileUrl }}"
                        class="btn rounded-xl px-4 text-sm font-semibold motion-standard"
                        style="min-height: 44px; background: var(--color-brand-500); color: #04201c">انسخ</button>
            </div>
        </label>
    </div>
</x-modal>
