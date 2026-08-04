@php
    /**
     * تابات المحفظة الثلاثة (19.2): رصيدي / المعاملات / المسحوبات.
     *
     * حسم التعارض مع 24.5 (دروب-داون بثلاث صفحات): **19.2 هو الحاكم** لأنّ
     * المواصفة الوظيفيّة تسبق دليل شكل الصفحات. ومع ذلك نحترم 2.15 «البساطة أوّلًا»:
     * التاب الذي لا يملكه المستخدم **لا يظهر أصلًا** (2.15-أ-7).
     */
    $me = auth()->user();
    $walletTabs = [];

    if ($me?->can('wallet.view')) {
        $walletTabs[] = ['key' => 'balance', 'label' => setting('wallet.tabs.balance', 'رصيدي'), 'url' => route('wallet.index')];
    }

    if ($me?->can('wallet.list')) {
        $walletTabs[] = ['key' => 'transactions', 'label' => setting('wallet.tabs.transactions', 'المعاملات'), 'url' => route('wallet.transactions')];
    }

    // 🔒 المسحوبات والأرباح مجموعة محميّة لمالك المنصّة وحده في مصفوفة الصلاحيّات
    if ($me?->can('withdraw.list') || $me?->can('earnings.view')) {
        $walletTabs[] = ['key' => 'withdrawals', 'label' => setting('wallet.tabs.withdrawals', 'المسحوبات'), 'url' => route('wallet.withdrawals')];
    }
@endphp

@if (count($walletTabs) > 1)
    <x-tabs :tabs="$walletTabs" :current="$current ?? 'balance'" />
@endif
