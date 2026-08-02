{{--
  رقائق تابات التطوّع الخمس (13.4-م · 10.0-د) — تُحقَن بعد تابات المتدرّب الأربعة
  في نفس شريط الـSticky، وعلى الموبايل تبقى **رقائق بسكرول أفقيّ** لأنّ الشريط نفسه
  `overflow-x-auto` (2.10.1-9). وما لا يملكه الزائر لا يظهر له أصلًا (2.15-أ-7).
--}}
@foreach ($tabs as $item)
    <a href="{{ $isOwner
            ? route('profile.me', ['tab' => $item['key']])
            : route('u.profile', ['code' => $owner->code, 'tab' => $item['key']]) }}"
       class="shrink-0 rounded-full px-4 py-2 text-sm motion-standard"
       style="{{ $active === $item['key']
            ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
            : 'background: var(--surface-raised); color: var(--text)' }}">{{ $item['label'] }}</a>
@endforeach
