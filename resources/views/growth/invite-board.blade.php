@extends('layouts.app')

@section('title', setting('growth.invite_board.title', 'متصدّرو الدعوات'))
@section('og_image', $cardUrl)

@section('content')
    <x-page-header :title="setting('growth.invite_board.title', 'متصدّرو الدعوات')"
                   :subtitle="$periodLabel">
        <x-slot:action>
            {{-- ⭐ اللوحة قابلة للاستخراج كصورة (توسعة 12.14-هـ) --}}
            <a href="{{ $cardUrl }}" download="invites-{{ $month }}.svg"
               class="hidden md:inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm motion-standard"
               style="background: var(--surface-sunken)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 4v11m0 0l-4-4m4 4l4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M5 19h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
                {{ setting('growth.invite_board.text_1', 'استخرج كصورة') }}
            </a>
        </x-slot:action>
    </x-page-header>

    {{-- فلتر واحد ظاهر: الشهر (2.15-أ-4) --}}
    <form method="get" class="mb-4">
        <label>
            <span class="sr-only">{{ setting('growth.invite_board.text_2', 'الشهر') }}</span>
            <select name="month" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($months as $key => $label)
                    <option value="{{ $key }}" @selected($key === $month)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </form>

    @if ($myRank)
        <p class="card p-3 text-sm mb-4">
            {{ str_replace('{rank}', $myRank, (string) setting('growth.invite_board.my_rank', 'ترتيبك الشهر ده: {rank}')) }}
        </p>
    @endif

    @if ($rows->isEmpty())
        <x-empty :message="setting('growth.invite_board.empty', 'مافيش دعوات مكتملة الشهر ده لسّه — ابدأ إنت.')"
                 action="{{ setting('growth.invite_board.action_1', 'ادعُ صديقك') }}"
                 :href="\Illuminate\Support\Facades\Route::has('referral.index') ? route('referral.index') : url('/referral')" />
    @else
        {{-- جدول على الشاشة الكبيرة، وكروت رأسيّة على الموبايل بلا تمرير أفقيّ (2.15-ج) --}}
        <div class="card overflow-hidden">
            <table class="hidden md:table w-full text-sm">
                <thead style="background: var(--surface-sunken)">
                    <tr class="text-xs" style="color: var(--text-muted)">
                        <th class="text-start p-3">#</th>
                        <th class="text-start p-3">{{ setting('growth.invite_board.text_3', 'الداعي') }}</th>
                        <th class="text-start p-3">{{ setting('growth.invite_board.text_4', 'دعوات مكتملة') }}</th>
                        <th class="text-start p-3">{{ setting('growth.invite_board.text_5', 'إجمالي الدعوات') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="p-3 font-bold tabular-nums">{{ $row['rank'] }}</td>
                            <td class="p-3">
                                @if ($row['user'])
                                    <a href="{{ $row['user']->profileUrl() }}" class="inline-flex items-center gap-2 hover:underline">
                                        <x-avatar :user="$row['user']" size="8" />
                                        <span class="font-semibold">{{ $row['user']->shortName() }}</span>
                                    </a>
                                @endif
                            </td>
                            <td class="p-3 tabular-nums">{{ $row['completed'] }}</td>
                            <td class="p-3 tabular-nums" style="color: var(--text-muted)">{{ $row['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="md:hidden">
                @foreach ($rows as $row)
                    <div class="p-3 flex items-center gap-3" style="border-top: 1px solid var(--border)">
                        <span class="font-bold tabular-nums w-6 shrink-0">{{ $row['rank'] }}</span>
                        @if ($row['user'])
                            <x-avatar :user="$row['user']" size="9" />
                            <div class="min-w-0">
                                <p class="text-sm font-semibold truncate">{{ $row['user']->shortName() }}</p>
                                <p class="text-xs" style="color: var(--text-muted)">
                                    {{ $row['completed'] }} {{ strtr((string) setting('growth.invite_board.text_6', 'مكتملة · :a1 إجمالي'), [':a1' => (string) ($row['total'])]) }}
                                </p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection

@section('mobile_action')
    <a href="{{ $cardUrl }}" download="invites-{{ $month }}.svg"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">{{ setting('growth.invite_board.text_7', 'استخرج اللوحة كصورة') }}</a>
@endsection
