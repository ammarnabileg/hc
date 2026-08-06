{{--
  كروت الشبكة الموحّدة وحدها (13.1 · 17 · 24.5) — تُستعمَل في الصفحة الكاملة
  أوّل تحميل وفي ردّ Fragment للتمرير التدريجيّ، فالماركب واحد لا نسختان.
--}}
@foreach ($cards as $card)
    @include('store.partials.card', ['card' => $card])
@endforeach
