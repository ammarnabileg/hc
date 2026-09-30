{{-- أيقونة التبويب والشاشة الرئيسيّة وملفّ تعريف التطبيق: النقطة الحمراء على الكريميّ (الهويّة 2.0) --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
<link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ route('manifest') }}">
<meta name="apple-mobile-web-app-title" content="{{ setting('ux.pwa.short_name', config('app.name')) }}">
