{{--
  ⭐ سياسة الاسترجاع — شاشة مستقلّة بصلاحيّة المورد `refunds` (12.2.2 · 12.2.3-أ-7)
  لا بصلاحيّة الماليّة المعزولة `finance.*`. يصلها المسؤول الماليّ وكلّ مَن يملك
  `refunds.view`/`refunds.edit` — والمحتوى نفسه المُستخدَم في تاب «سياسة الاسترجاع»
  داخل 🔒 الماليّات (نفس المفتاح، نفس الـAudit، لا Drift).

  ⬇︎ خارج نصّ 12.0 الحرفيّ: الخريطة تضع «سياسة الاسترجاع» تحت 🔒 الماليّات
  (owner-only) وحدها، وهذه شاشةٌ إضافيّة تسدّ فجوة 12.2.3-أ-7 (قدرةٌ في المصفوفة
  كانت بلا شاشة — database/data/_STATUS.md).
--}}
@extends('layouts.admin')

@section('title', setting('admin.store.refund_policy.index.syast_alastrjaa', 'سياسة الاسترجاع'))

@section('content')
    <x-page-header :title="setting('admin.store.refund_policy.index.syast_alastrjaa', 'سياسة الاسترجاع')"
                   :subtitle="setting('admin.store.refund_policy.index.subtitle', 'عرض نصّ سياسة عدم الاسترجاع بنسختيه وتحريره ومعاينته وأماكن ظهوره (12.2.2 · 19.4).')"
                   :breadcrumbs="[
                       ['label' => setting('admin.store.refund_policy.index.lwha_alidara', 'لوحة الإدارة'), 'url' => url('/admin')],
                       ['label' => setting('admin.store.refund_policy.index.almtjr_walmalyat', 'المتجر والماليّات'), 'url' => route('admin.store.index')],
                       ['label' => setting('admin.store.refund_policy.index.syast_alastrjaa', 'سياسة الاسترجاع')],
                   ]" />

    <div class="space-y-4">
        @include('admin.store.finance.refund-policy', ['policyFormRoute' => 'admin.refund-policy.save'])
    </div>
@endsection
