{{--
  ردّ Fragment للتمرير التدريجيّ (13.1 · قرار §25) — هدفان متوازيان (جدول
  سطح المكتب + كروت الموبايل)، فكلّ نصفٍ يُغلَّف بـ<template data-for> يطابق
  Selector الحاوية الحقيقيّة كما يقرؤه سكربت `partials/load-more`.
--}}
@php
    use App\Http\Controllers\Trainee\WalletController;

    $flow = fn ($row) => WalletController::flowOf($row);

    $panel = fn ($row) => [
        'date' => $row->created_at?->format('Y-m-d H:i'),
        'type' => WalletController::sourceLabels()[$row->source] ?? $row->source,
        'currency' => $row->currency?->name_ar,
        'amount' => (float) $row->amount,
        'applied' => (float) ($row->applied_amount ?? $row->amount),
        'balance_after' => (float) $row->balance_after,
        'reason' => $row->reason,
        'flow' => $flow($row)['from'].' ← '.$flow($row)['to'],
        'notes' => WalletController::notesOf($row),
        'reference' => $row->reference_type ? class_basename($row->reference_type).'#'.$row->reference_id : null,
        'capped' => (bool) $row->exceeded_daily_cap,
        'correction' => (bool) $row->is_correction,
        'invoice' => 'INV-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT),
    ];
@endphp
<template data-for="[data-wallet-tx-desktop]">@include('wallet.partials.transactions-rows-desktop', ['rows' => $rows])</template>
<template data-for="[data-wallet-tx-mobile]">@include('wallet.partials.transactions-rows-mobile', ['rows' => $rows])</template>
