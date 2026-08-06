{{--
  ردّ Fragment للتمرير التدريجيّ (13.1 · قرار §25) — هدفان متوازيان (جدول
  سطح المكتب + كروت الموبايل)، بنفس أسلوب `transactions-rows-fragment`.
--}}
@php
    $num = fn ($v, $d = 2) => number_format((float) $v, $d);
@endphp
<template data-for="[data-wallet-wd-desktop]">@include('wallet.partials.withdrawals-rows-desktop', ['rows' => $rows])</template>
<template data-for="[data-wallet-wd-mobile]">@include('wallet.partials.withdrawals-rows-mobile', ['rows' => $rows])</template>
