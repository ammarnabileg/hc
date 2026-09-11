@extends('layouts.admin')

@section('title', setting('admin.gamification.index.altlayb_walthdyat', 'التلعيب والتحديات'))

@section('content')
    <x-page-header
        :title="setting('admin.gamification.index.altlayb_walthdyat', 'التلعيب والتحديات')"
        :subtitle="setting('admin.gamification.index.aqtsad_xp_waltdhakr_walsharat_walstryks', 'اقتصاد XP والتذاكر والشارات والستريكس والليدر بورد والحروب والاحتفالات — كلّه إعدادات.')"
        :breadcrumbs="[['label' => setting('admin.gamification.index.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')], ['label' => setting('admin.gamification.index.altlayb', 'التلعيب')]]" />

    {{-- وجهات مجموعة «التلعيب والتحديات» العشر (12.0 · 12.10 موسّع) — من مصدر السايد بار نفسه --}}
    {{--
      وتفصيلًا: البنود من `GamificationController::menu()` وحده — هو نفسه ما
      يبني به السايد بار مجموعته، فلا يفترق الشريط عن الدروب-داون. وسبعٌ منها
      تابٌّ هنا يُحمَّل كسولًا (2.15-د)، وثلاثٌ شاشاتٌ مستقلّة بمسارها (بنك أسئلة
      الحروب · الريفيرال · الرسائل الإيجابيّة) تُعلَّم بسهمٍ لأنّها تغادر الصفحة.
      والمحظور **يُخفى ولا يُعطَّل** (2.15-أ-7).
    --}}
    <div class="sticky-bar -mx-4 md:mx-0 px-4 md:px-0 py-2 mb-4" style="background: var(--surface)">
        <nav class="flex gap-2 min-w-0 overflow-x-auto no-scrollbar"
             aria-label="{{ setting('nav.admin.group_gamification', 'التلعيب والتحديات') }}">
            @foreach ($menu as $item)
                @php
                    $here = $item['route'] === 'admin.gamification.index';
                    $current = $here && ($item['params']['tab'] ?? null) === $tab;
                @endphp
                <a href="{{ $item['href'] }}"
                   @if ($current) aria-current="page" @endif
                   class="shrink-0 rounded-full px-4 py-2 text-sm motion-standard"
                   style="{{ $current
                        ? 'background: var(--color-brand-500); color:#04201c; font-weight:700'
                        : 'background: var(--surface-raised); color: var(--text)' }}">
                    {{ $item['label'] }}@unless ($here)<span class="text-xs opacity-60"> ↗</span>@endunless
                </a>
            @endforeach

            {{--
              تابٌ ليس وجهةً في 12.0 (المستويات — قسمٌ داخل «XP والتذاكر») يُفتَح
              برابطٍ قديم محفوظ: يُعلَّم في الشريط حتى لا يضيع «أنت هنا» (2.15-أ).
            --}}
            @unless (in_array($tab, $menuTabs, true))
                <span aria-current="page" class="shrink-0 rounded-full px-4 py-2 text-sm"
                      style="background: var(--color-brand-500); color:#04201c; font-weight:700">{{ $tabs[$tab] ?? '' }}</span>
            @endunless
        </nav>
    </div>

    @include('admin.gamification.tabs.'.$tab)
@endsection
