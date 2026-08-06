{{--
  كروت الفعاليّات وحدها (13.1 · 24.5) — الصفحة الكاملة أوّل تحميل وردّ
  Fragment للتمرير التدريجيّ يستعملان نفس الماركب.
--}}
@foreach ($events as $event)
    @include('events.components.card', [
        'event' => $event,
        'presenter' => $presenter,
        'myRegistrations' => $myRegistrations,
    ])
@endforeach
