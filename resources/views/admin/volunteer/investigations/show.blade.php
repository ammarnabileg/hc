@extends('layouts.admin')

@section('title', setting('admin.volunteer.investigations.show.mlf_althqyq', 'ملفّ التحقيق'))

@section('content')
    <x-page-header
        :title="($case->user?->name ?? '').' — '.setting('admin.volunteer.investigations.show.mlf_althqyq', 'ملفّ التحقيق')"
        :subtitle="setting('admin.volunteer.investigations.show.mqada_allgna_yqrran_wmshrf_aam_alttwa_yhsm', 'مقعدا اللجنة يقرّران، ومشرف عام التطوّع وحده يحسم — وكلّ قرار بمبرّر مكتوب.')"
        :breadcrumbs="[['label' => setting('admin.volunteer.investigations.show.alttwa', 'التطوّع'), 'url' => route('admin.volunteer.index')], ['label' => setting('admin.volunteer.investigations.show.lgna_althqyq', 'لجنة التحقيق'), 'url' => route('admin.volunteer.investigations.index')], ['label' => $case->user?->name ?? '']]">
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-[1fr_320px]">
        <div class="space-y-4">
            {{-- الحالة --}}
            <div class="card p-4 flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <x-state-badge state="warn" :label="$case->status" />
                    <span class="text-xs ms-2" style="color: var(--text-muted)">{{ setting('admin.volunteer.investigations.show.atfaal_fy', 'اتفعّل في') }} {{ $case->activated_at?->format('Y-m-d H:i') }}</span>
                </div>
            </div>

            {{-- ملفّ القضيّة المتجمّع آليًّا --}}
            <div class="card p-4">
                <h2 class="font-bold text-sm mb-2">{{ setting('admin.volunteer.investigations.show.mlf_alqdya', 'ملفّ القضيّة') }}</h2>
                <p class="text-xs mb-2" style="color: var(--text-muted)">
                    {{ setting('admin.volunteer.investigations.show.nafdha', 'نافذة') }} {{ $case->dossier_snapshot['window_days'] ?? '—' }} {{ setting('admin.volunteer.investigations.show.ywm_mn_almamlat', 'يوم من المعاملات') }}
                </p>
                <div class="space-y-1 max-h-64 overflow-y-auto text-xs">
                    @forelse (($case->dossier_snapshot['transactions'] ?? []) as $row)
                        <div class="flex items-center justify-between gap-2 py-1" style="border-bottom: 1px solid var(--border)">
                            <span>{{ $row['reason'] }}</span>
                            <span style="color: {{ ($row['amount'] ?? 0) < 0 ? 'var(--color-state-danger)' : 'var(--color-state-ok)' }}">{{ $row['amount'] }} {{ $row['currency'] }}</span>
                        </div>
                    @empty
                        <span style="color: var(--text-muted)">{{ setting('admin.volunteer.investigations.show.la_mamlat_fy_alnafdha', 'لا معاملات في النافذة.') }}</span>
                    @endforelse
                </div>
            </div>

            {{-- الميتينج --}}
            @if ($case->status === 'open')
                <div class="card p-4">
                    <h2 class="font-bold text-sm mb-2">{{ setting('admin.volunteer.investigations.show.almytynj_alawl', 'الميتينج الأوّل') }}</h2>
                    @if ($case->meeting_scheduled_at)
                        <p class="text-sm mb-2">{{ setting('admin.volunteer.investigations.show.almyad', 'الميعاد:') }} {{ $case->meeting_scheduled_at->format('Y-m-d H:i') }}
                            @if ($case->reschedule_count > 0)
                                <span class="text-xs" style="color: var(--text-muted)">({{ setting('admin.volunteer.investigations.show.aad_gdwlta', 'أُعيدت جدولته') }})</span>
                            @endif
                        </p>
                    @endif
                    <form method="post" action="{{ route('admin.volunteer.investigations.meeting', $case) }}" class="flex items-end gap-2 flex-wrap">
                        @csrf
                        <div>
                            <label class="block text-xs mb-1" for="meeting-at" style="color: var(--text-muted)">{{ setting('admin.volunteer.investigations.show.myad_gdyd', 'ميعاد جديد') }}</label>
                            <input id="meeting-at" type="datetime-local" name="at" required
                                   class="rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        </div>
                        <button type="submit" class="btn rounded-xl px-3 py-2 text-sm font-semibold" style="background: var(--surface-raised)">{{ setting('admin.volunteer.investigations.show.hdd', 'حدّد') }}</button>
                    </form>
                </div>
            @endif

            {{-- قرار الميتينج --}}
            @if ($canRecordVerdict && $case->status === 'open')
                <div class="card p-4">
                    <h2 class="font-bold text-sm mb-2">{{ setting('admin.volunteer.investigations.show.qrar_almytynj', 'قرار الميتينج') }}</h2>
                    <form method="post" action="{{ route('admin.volunteer.investigations.verdict', $case) }}" class="space-y-2">
                        @csrf
                        <select name="verdict" required class="w-full rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="chance">{{ setting('admin.volunteer.investigations.show.frsa', 'فرصة (+1 لمعدّل الالتزام وإعادة تفعيل)') }}</option>
                            <option value="recommend_dismissal">{{ setting('admin.volunteer.investigations.show.twsya_biqsa', 'توصية إقصاء — تُرفَع لمشرف عام التطوّع') }}</option>
                        </select>
                        <textarea name="reason" required minlength="3" maxlength="1000" rows="2" placeholder="{{ setting('admin.volunteer.investigations.show.mbrr_mktwb_ilzamy', 'مبرّر مكتوب — إلزاميّ') }}"
                                  class="w-full rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.investigations.show.sjl_alqrar', 'سجّل القرار') }}</button>
                    </form>
                </div>
            @endif

            {{-- القرار البشريّ النهائيّ --}}
            @if ($canDecide && $case->status === 'recommendation_raised')
                <div class="card p-4">
                    <h2 class="font-bold text-sm mb-2">{{ setting('admin.volunteer.investigations.show.alqrar_albshry_alnhay', 'القرار البشريّ النهائيّ') }}</h2>
                    <p class="text-xs mb-2" style="color: var(--text-muted)">{{ $case->verdict_reason }}</p>
                    <form method="post" action="{{ route('admin.volunteer.investigations.decision', $case) }}" class="space-y-2">
                        @csrf
                        <select name="decision" required class="w-full rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="dismiss">{{ setting('admin.volunteer.investigations.show.iqsa', 'إقصاء') }}</option>
                            <option value="reject_recommendation">{{ setting('admin.volunteer.investigations.show.rfd_altwsya_frsa_bdlha', 'رفض التوصية (فرصة بدلًا منها)') }}</option>
                        </select>
                        <textarea name="reason" required minlength="3" maxlength="1000" rows="2" placeholder="{{ setting('admin.volunteer.investigations.show.mbrr_mktwb_ilzamy', 'مبرّر مكتوب — إلزاميّ') }}"
                                  class="w-full rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"></textarea>
                        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--color-brand-500); color: #04201c">{{ setting('admin.volunteer.investigations.show.sjl_alqrar', 'سجّل القرار') }}</button>
                    </form>
                </div>
            @endif

            @if ($canArchive && $case->status === 'decision_issued')
                <form method="post" action="{{ route('admin.volunteer.investigations.archive', $case) }}">
                    @csrf
                    <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--surface-raised)">{{ setting('admin.volunteer.investigations.show.aqfl_warshf', 'اقفل وأرشِف') }}</button>
                </form>
            @endif

            @if ($case->status === 'closed')
                <div class="card p-4 text-sm">
                    <strong>{{ setting('admin.volunteer.investigations.show.mghlq', 'مغلق') }}</strong>
                    — {{ $case->decision === 'dismiss' ? setting('admin.volunteer.investigations.show.iqsa', 'إقصاء') : ($case->verdict === 'chance' ? setting('admin.volunteer.investigations.show.frsa', 'فرصة') : setting('admin.volunteer.investigations.show.rfd_altwsya', 'رفض التوصية')) }}
                </div>
            @endif
        </div>

        <div class="space-y-4">
            {{-- المقعدان --}}
            <div class="card p-4">
                <h2 class="font-bold text-sm mb-2">{{ setting('admin.volunteer.investigations.show.almqadan', 'المقعدان') }}</h2>
                <div class="text-sm mb-1">{{ setting('admin.volunteer.investigations.show.alablayn', 'الأبلاين:') }} {{ $case->seatUpline?->name ?? '—' }}</div>
                <div class="text-sm mb-2">{{ setting('admin.volunteer.investigations.show.qsm_almttwan', 'قسم المتطوّعين:') }} {{ $case->seatDept?->name ?? '—' }}</div>

                @if ($canSeatAssign && $case->status === 'open')
                    <form method="post" action="{{ route('admin.volunteer.investigations.seats', $case) }}" class="space-y-2 mt-2">
                        @csrf
                        <select name="slot" class="w-full rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                            <option value="upline">{{ setting('admin.volunteer.investigations.show.mqad_alablayn', 'مقعد الأبلاين') }}</option>
                            <option value="dept">{{ setting('admin.volunteer.investigations.show.mqad_qsm_almttwan', 'مقعد قسم المتطوّعين') }}</option>
                        </select>
                        <input type="text" name="code" required maxlength="32" placeholder="{{ setting('admin.volunteer.investigations.show.kwd_alaadyl', 'كود العضو (تجاوز الاختيار)') }}"
                               class="w-full rounded-xl px-3 py-2 text-sm" style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                        <button type="submit" class="btn rounded-xl px-3 py-2 text-sm font-semibold w-full" style="background: var(--surface-raised)">{{ setting('admin.volunteer.investigations.show.tgawz', 'تجاوز') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
