@extends('layouts.app')

@section('title', 'التذاكر')

@section('content')
    <x-page-header
        title="التذاكر 🎟️"
        subtitle="التذكرة عملة تفاعل: بتكسبها من تعلّمك، وبتصرفها على اللي يهمّك."
        :breadcrumbs="[['label' => 'المحفظة', 'url' => route('wallet.index')], ['label' => 'التذاكر']]">
        <x-slot:action>
            <a href="{{ route('wallet.transactions', ['currency' => 'tickets']) }}"
               class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">حركة تذاكري</a>
        </x-slot:action>
    </x-page-header>

    <section class="card p-5 md:p-6 animate-fadeup">
        <div class="text-sm" style="color: var(--text-muted)">رصيد التذاكر</div>
        <div class="mt-1 text-4xl font-extrabold"
             data-count-to="{{ number_format($balance, (int) ($currency?->decimals ?? 0)) }}">{{ number_format($balance, (int) ($currency?->decimals ?? 0)) }}</div>
    </section>

    {{-- عمودان على الشاشة الكبيرة، وشاشةٌ واحدة متتابعة على الموبايل (2.15-ج) --}}
    <section class="grid md:grid-cols-2 gap-4 mt-4">
        <div class="card p-4 md:p-5">
            <h2 class="font-bold mb-3">إزّاي تكسب تذاكر</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($earnSources as $item)
                    <li class="flex items-start gap-2">
                        <span aria-hidden="true" style="color: var(--color-state-ok)">●</span>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card p-4 md:p-5">
            <h2 class="font-bold mb-3">تصرفها فين</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($spendTargets as $item)
                    <li class="flex items-start gap-2">
                        <span aria-hidden="true" style="color: var(--text-muted)">○</span>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="card mt-4 p-4 md:p-5">
        <h2 class="font-bold mb-3">آخر حركات التذاكر</h2>

        @forelse ($recent as $row)
            <div class="flex items-center justify-between gap-3 py-3 {{ $loop->last ? '' : 'border-b' }}"
                 style="border-color: var(--border)">
                <div class="min-w-0">
                    <div class="text-sm font-semibold truncate">{{ $row->reason ?: (\App\Http\Controllers\Trainee\WalletController::SOURCE_LABELS[$row->source] ?? $row->source) }}</div>
                    <div class="text-xs mt-0.5" style="color: var(--text-muted)"
                         title="{{ $row->created_at?->format('Y-m-d H:i') }}">{{ $row->created_at?->diffForHumans() }}</div>
                </div>
                @include('wallet.components.amount', ['value' => $row->applied_amount ?? $row->amount])
            </div>
        @empty
            <x-empty message="لسّه مافيش تذاكر — أوّل درس هيجيبلك أوّل تذكرة."
                     action="ابدأ تعلّمك" :href="route('dashboard')" />
        @endforelse
    </section>
@endsection
