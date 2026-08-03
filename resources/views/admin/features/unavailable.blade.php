{{--
  الصفحة التي يراها المستخدم حين تكون الميزة موقوفة **وسلوكها «إظهار رسالة»**
  (24.3). أمّا سلوك «إخفاء كامل» فلا يصل إلى هنا أصلًا — يخرج 404.

  والنصّ نصُّ الميزة نفسها (ع/إ) الذي كتبه الأدمن في بوب-أب الإيقاف، وإلّا
  فالنصّ الافتراضيّ العامّ — لا حرفَ محروق.
--}}
@extends('layouts.app')

@section('title', setting('features.ui.unavailable.title', 'الميزة دي واقفة دلوقتي'))
@section('noindex', true)

@section('content')
    <div class="card p-8 text-center max-w-xl mx-auto">
        {{-- أيقونة SVG مرسومة بهويّة المنصّة — بلا أيّ مكتبة أيقونات --}}
        <svg width="48" height="48" viewBox="0 0 48 48" fill="none" aria-hidden="true" class="mx-auto"
             style="color: var(--color-state-warn)">
            <circle cx="24" cy="24" r="19" stroke="currentColor" stroke-width="2.5" opacity=".35"/>
            <path d="M18 18v12M30 18v12" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/>
        </svg>

        <h1 class="mt-4 text-lg font-bold">{{ setting('features.ui.unavailable.title', 'الميزة دي واقفة دلوقتي') }}</h1>
        <p class="mt-2 text-sm" style="color: var(--text-muted)">{{ $message }}</p>

        <a href="{{ url('/dashboard') }}"
           class="btn inline-flex items-center justify-center mt-6 rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
           style="background: var(--color-brand-500); color: #04201c">{{ setting('features.ui.unavailable.back', 'ارجع للرئيسيّة') }}</a>
    </div>
@endsection
