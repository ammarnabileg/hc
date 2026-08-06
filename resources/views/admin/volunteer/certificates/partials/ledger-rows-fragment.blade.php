{{--
  ردّ Fragment للتمرير التدريجيّ (13.1 · قرار §25) — ثلاثة أهداف متوازية
  (جدول سطح المكتب + كروت الموبايل + نوافذ «عرض» لكلّ شهادة)، وكلّ ثلثٍ
  يُغلَّف بـ<template data-for> يطابق Selector الحاوية الحقيقيّة.
--}}
<template data-for="[data-cert-ledger-desktop]">@include('admin.volunteer.certificates.partials.ledger-rows-desktop', ['issued' => $issued])</template>
<template data-for="[data-cert-ledger-mobile]">@include('admin.volunteer.certificates.partials.ledger-rows-mobile', ['issued' => $issued])</template>
<template data-for="[data-cert-ledger-modals]">@include('admin.volunteer.certificates.partials.ledger-modals', ['issued' => $issued])</template>
