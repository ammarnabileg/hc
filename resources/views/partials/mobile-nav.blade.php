{{--
  تنقّل سفليّ على الموبايل — أربع وجهات فقط (تصحيح ملفّ الهويّة 2.2،
  سبتمبر 2026 · 2.10.1-13): الرئيسيّة · تعلّمي · مكتبتي · حسابي. كان
  الموبايل بلا بديلٍ للتنقّل حين تختفي القوائم — هذا الشريط الثابت يحلّها.

  الحالة النشطة من المسار الحاليّ على الخادم مباشرةً (routeIs) لا بجافاسكربت،
  فتصحّ من أوّل رسمٍ للصفحة بلا وميض.
--}}
@php
    $mobileNavItems = [
        ['route' => 'dashboard', 'match' => ['dashboard'], 'label' => setting('nav.mobile.item_dashboard', 'الرئيسيّة'), 'icon' => 'home'],
        ['route' => 'learning.courses', 'match' => ['learning.*'], 'label' => setting('nav.mobile.item_learning', 'تعلّمي'), 'icon' => 'course'],
        ['route' => 'library.index', 'match' => ['library.*'], 'label' => setting('nav.mobile.item_library', 'مكتبتي'), 'icon' => 'library'],
        ['route' => 'profile.me', 'match' => ['profile.*', 'settings.*'], 'label' => setting('nav.mobile.item_account', 'حسابي'), 'icon' => 'user'],
    ];
@endphp

<nav id="mobile-navigation" aria-label="{{ setting('nav.mobile.aria', 'التنقّل السريع') }}">
    @foreach ($mobileNavItems as $item)
        @php $active = request()->routeIs(...$item['match']); @endphp
        <a href="{{ \Illuminate\Support\Facades\Route::has($item['route']) ? route($item['route']) : '#' }}"
           class="mobile-nav-item {{ $active ? 'active' : '' }}"
           @if ($active) aria-current="page" @endif>
            <x-icon :name="$item['icon']" size="21" />
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
