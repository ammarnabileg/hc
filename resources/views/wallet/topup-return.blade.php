@extends('layouts.app')

@section('title', setting('wallet.topup_return.title', 'حالة الدفع'))

@php
    /*
     | ⭐ صفحة عرضٍ فقط: لا تضيف رصيدًا أبدًا (19.5-ج-2).
     | الرصيد ينزل من الويب هوك الموقَّع وحده، فالنصّ هنا يطمئن المستخدم لا أكثر.
     */
    $view = match ($state) {
        'success' => [
            'state' => 'ok',
            'title' => setting('wallet.topup_return.success_title', 'استلمنا دفعتك'),
            'body' => setting('wallet.topup_return.success_body', 'رصيدك هيتحدّث أوّل ما يوصلنا تأكيد البوّابة، وهيوصلك إشعار.'),
        ],
        'fail' => [
            'state' => 'danger',
            'title' => setting('wallet.topup_return.fail_title', 'الدفع ما تمّش'),
            'body' => setting('wallet.topup_return.fail_body', 'ما اتخصمش منك حاجة. تقدر تجرّب تاني أو تستعمل التحويل اليدويّ.'),
        ],
        default => [
            'state' => 'warn',
            'title' => setting('wallet.topup_return.pending_title', 'الدفع تحت التأكيد'),
            'body' => setting('wallet.topup_return.pending_body', 'لسّه بننتظر تأكيد البوّابة. سيبها علينا وهنبلّغك.'),
        ],
    };
@endphp

@section('content')
    <x-page-header
        :title="setting('wallet.topup_return.title', 'حالة الدفع')"
        :breadcrumbs="[['label' => setting('wallet.index.breadcrumb_root', 'المحفظة'), 'url' => route('wallet.index')], ['label' => setting('wallet.topup.title', 'شحن الحساب'), 'url' => route('wallet.topup')], ['label' => setting('wallet.topup_return.title', 'حالة الدفع')]]" />

    <section class="card p-6 text-center animate-fadeup">
        <div class="flex justify-center"><x-state-badge :state="$view['state']" :label="$view['title']" /></div>
        <p class="mt-3 text-sm" style="color: var(--text-muted)">{{ $view['body'] }}</p>

        <div class="mt-5 flex items-center justify-center gap-2 flex-wrap">
            <a href="{{ route('wallet.index') }}"
               class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">{{ setting('wallet.tabs.balance', 'رصيدي') }}</a>
            <a href="{{ route('wallet.transactions') }}"
               class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--surface-raised); color: var(--text)">{{ setting('wallet.tabs.transactions', 'المعاملات') }}</a>
        </div>
    </section>
@endsection
