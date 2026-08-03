@extends('layouts.app')

@section('title', 'معاملاتي')

@php
    /**
     * معاملاتي — Rep / VXP (24.4 · 13.4-ط).
     * كشف موحّد بكلّ حركة، **مفلترًا على جانب التطوّع وحده**.
     */
    $repState = $rep >= 0 ? ($rep >= rep_rule('limit.club_threshold') ? 'honor' : 'ok')
        : ($rep <= rep_rule('limit.red_indicator') ? 'danger' : 'warn');
    $span = max(0.01, $repMax - $repMin);
    $repPercent = max(0, min(100, (($rep - $repMin) / $span) * 100));
    $dailyCap = rep_rule('limit.daily_loss');

    $valueOf = fn ($t) => (float) ($t->applied_amount ?? $t->amount);
    $sign = fn (float $v) => $v > 0 ? '+' : ($v < 0 ? '−' : '');
    $symbol = fn (float $v) => $v > 0 ? '●' : ($v < 0 ? '◉' : '○');
    $colorOf = fn (float $v) => $v > 0 ? 'ok' : ($v < 0 ? 'danger' : 'idle');
@endphp

@section('content')
    <x-page-header
        title="معاملاتي"
        subtitle="كلّ حركة على درجتك ونقاطك بسببها ومرجعها"
        :breadcrumbs="[['label' => 'لوحة التطوّع', 'url' => url('/dashboard')], ['label' => 'معاملاتي']]">
        <x-slot:action>
            <a href="{{ route('volunteer.transactions.export', ['days' => $filters['days']]) }}"
               class="btn inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold motion-standard"
               style="background: var(--color-brand-500); color: #04201c">تصدير كشف</a>
        </x-slot:action>
    </x-page-header>

    {{-- كارتان فقط: Rep بجيج −10…+10 · VXP التراكميّ (2.15-أ-3) --}}
    <div class="grid gap-3 sm:grid-cols-2 mb-4">
        <div class="card p-4 animate-fadeup">
            <div class="flex items-center justify-between">
                <span class="text-sm" style="color: var(--text-muted)">درجة الالتزام (Rep)</span>
                <x-state-badge :state="$repState" :label="$sign($rep).abs($rep)" />
            </div>
            <div class="mt-2 text-2xl font-extrabold" data-count-to="{{ $rep }}">{{ $rep }}</div>
            <div class="mt-3 h-2 rounded-full relative" style="background: var(--surface-sunken)">
                <span class="absolute -top-1 h-4 w-1 rounded-full"
                      style="inset-inline-start: {{ $repPercent }}%; background: var(--color-state-{{ $repState }})"></span>
            </div>
            <div class="mt-1 flex justify-between text-xs" style="color: var(--text-muted)">
                <span>{{ $repMin }}</span><span>{{ $repMax }}</span>
            </div>
        </div>

        <x-kpi label="VXP التراكميّ" :value="$vxp" icon="◆" hint="نقاط الإنتاج — تراكميّة لا تتصفّر" />
    </div>

    <x-filters :action="route('volunteer.transactions')">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">النوع</span>
            <select name="type" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                <option value="rep" @selected($filters['type'] === 'rep')>Rep</option>
                <option value="vxp" @selected($filters['type'] === 'vxp')>VXP</option>
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">المصدر</span>
            <select name="source" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($sources as $key => $label)
                    <option value="{{ $key }}" @selected($filters['source'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="objectable" value="1" @checked($filters['objectable'])
                   onchange="this.form.submit()" style="accent-color: var(--color-brand-500)">
            قابلة للاعتراض الآن
        </label>

        <label class="text-sm flex-1 min-w-40">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="بالسبب أو رقم المرجع…"
                   class="w-full rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
        </label>

        <x-slot:advanced>
            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">الفترة (أيّام)</span>
                <input type="number" name="days" min="1" max="365" value="{{ $filters['days'] }}"
                       class="rounded-xl px-3 py-2 text-sm w-28"
                       style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
            </label>
            <label class="text-sm">
                <span class="block text-xs mb-1" style="color: var(--text-muted)">الكيان</span>
                <select name="entity" class="rounded-xl px-3 py-2 text-sm"
                        style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)">
                    <option value="">الكلّ</option>
                    @foreach ($entities as $entity)
                        <option value="{{ $entity->id }}" @selected($filters['entity'] === $entity->id)>{{ $entity->name_ar }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="btn rounded-xl px-4 py-2 text-sm" style="border: 1px solid var(--border)">تطبيق</button>
        </x-slot:advanced>
    </x-filters>

    @if ($rows->isEmpty())
        <x-empty message="مفيش معاملات في المدى ده — وسّع المدى وشوف" />
    @else
        <div class="card min-w-0 overflow-x-auto hidden md:block">
            <table class="w-full text-sm"
                   {{-- حدّ الأعمدة الافتراضيّ من الإعدادات، و«وضع متقدّم» يرفعه (2.15-أ-5) --}}
                   @unless (advanced_mode()) data-columns-cap="{{ view_mode()->defaultColumns() }}" @endunless>
                <thead style="color: var(--text-muted)">
                    <tr>
                        <th class="p-3 text-start">التاريخ/الساعة</th>
                        <th class="p-3 text-start">النوع</th>
                        <th class="p-3 text-start">القيمة</th>
                        <th class="p-3 text-start">السبب</th>
                        <th class="p-3 text-start">المرجع</th>
                        <th class="p-3 text-start">الاعتراض</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $value = $valueOf($row);
                            $objection = $objections[$row->id] ?? null;
                            $left = $service->daysLeft($row);
                        @endphp
                        <tr style="border-top: 1px solid var(--border)">
                            <td class="p-3 whitespace-nowrap" title="{{ $row->created_at?->format('Y-m-d H:i') }}">
                                {{ $row->created_at?->format('Y-m-d · H:i') }}
                            </td>
                            <td class="p-3">{{ $row->currency?->name_en ?? $row->currency?->name_ar }}</td>
                            <td class="p-3 font-bold whitespace-nowrap"
                                style="color: var(--color-state-{{ $colorOf($value) }})">
                                <span aria-hidden="true">{{ $symbol($value) }}</span>
                                {{ $sign($value) }}{{ abs($value) }}
                            </td>
                            <td class="p-3">
                                <span>{{ $row->reason }}</span>
                                <span class="inline-flex flex-wrap gap-1 ms-1">
                                    @if ($row->exceeded_daily_cap)
                                        <x-state-badge state="warn" :label="'تخطّت حدّ الخسارة اليوميّ '.$dailyCap" />
                                    @endif
                                    @if ($row->is_correction)
                                        <x-state-badge state="ok" label="مصحِّحة" />
                                    @endif
                                </span>
                            </td>
                            <td class="p-3">
                                @include('volunteer.transactions.partials.reference', ['row' => $row])
                            </td>
                            <td class="p-3">
                                @if ($objection)
                                    <a href="{{ route('volunteer.objections', ['objection' => $objection->id]) }}"
                                       class="btn inline-flex rounded-xl px-3 py-1.5 text-xs" style="border: 1px solid var(--border)">عرض الاعتراض</a>
                                @elseif ($left !== null)
                                    <button type="button" data-modal-open="obj-{{ $row->id }}"
                                            class="btn inline-flex items-center gap-1 rounded-xl px-3 py-1.5 text-xs"
                                            style="border: 1px solid var(--border)">
                                        اعتراض <span style="color: var(--text-muted)">· باقي {{ $left }} أيّام</span>
                                    </button>
                                @else
                                    <span class="text-xs" style="color: var(--text-muted)">انتهت مهلة الاعتراض</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- الموبايل: كروت رأسيّة بأهمّ 3 حقول والباقي بالتوسيع (2.15-ج) --}}
        <div class="space-y-2 md:hidden">
            @foreach ($rows as $row)
                @php
                    $value = $valueOf($row);
                    $objection = $objections[$row->id] ?? null;
                    $left = $service->daysLeft($row);
                @endphp
                <details class="card p-3">
                    <summary class="flex items-center justify-between gap-2 cursor-pointer">
                        <span class="text-sm truncate">{{ $row->reason }}</span>
                        <strong class="whitespace-nowrap" style="color: var(--color-state-{{ $colorOf($value) }})">
                            <span aria-hidden="true">{{ $symbol($value) }}</span> {{ $sign($value) }}{{ abs($value) }}
                        </strong>
                    </summary>
                    <div class="mt-2 space-y-2 text-xs" style="color: var(--text-muted)">
                        <div>{{ $row->created_at?->format('Y-m-d · H:i') }} · {{ $row->currency?->name_ar }}</div>
                        <div>@include('volunteer.transactions.partials.reference', ['row' => $row])</div>
                        @if ($row->exceeded_daily_cap)
                            <x-state-badge state="warn" :label="'تخطّت حدّ الخسارة اليوميّ '.$dailyCap" />
                        @endif
                        @if ($row->is_correction)
                            <x-state-badge state="ok" label="مصحِّحة" />
                        @endif
                        @if ($objection)
                            <a href="{{ route('volunteer.objections', ['objection' => $objection->id]) }}"
                               class="btn inline-flex rounded-xl px-3 py-1.5" style="border: 1px solid var(--border)">عرض الاعتراض</a>
                        @elseif ($left !== null)
                            <button type="button" data-modal-open="obj-{{ $row->id }}"
                                    class="btn inline-flex rounded-xl px-3 py-1.5" style="border: 1px solid var(--border)">اعتراض · باقي {{ $left }} أيّام</button>
                        @else
                            <span>انتهت مهلة الاعتراض</span>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>
    @endif

    @push('modals')
        @foreach ($rows as $row)
            @if (! isset($objections[$row->id]) && $service->daysLeft($row) !== null)
                <x-modal :id="'obj-'.$row->id" title="اعتراض على معاملة">
                    <form method="post" action="{{ route('volunteer.objections.store') }}"
                          enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <input type="hidden" name="transaction_id" value="{{ $row->id }}">

                        <div class="rounded-xl p-3 text-sm" style="background: var(--surface-sunken)">
                            <div class="font-semibold">{{ $row->reason }}</div>
                            <div class="text-xs mt-1" style="color: var(--text-muted)">
                                {{ $row->created_at?->format('Y-m-d H:i') }} ·
                                {{ $row->currency?->name_ar }} ·
                                {{ $sign($valueOf($row)) }}{{ abs($valueOf($row)) }}
                            </div>
                        </div>

                        <label class="block text-sm">
                            <span class="block text-xs mb-1" style="color: var(--text-muted)">سبب الاعتراض (إلزاميّ)</span>
                            <textarea name="reason" rows="4" required minlength="5" maxlength="2000"
                                      class="w-full rounded-xl px-3 py-2 text-sm"
                                      style="background: var(--surface-raised); border: 1px solid var(--border); color: var(--text)"></textarea>
                        </label>

                        <label class="block text-sm">
                            <span class="block text-xs mb-1" style="color: var(--text-muted)">مرفق (اختياريّ)</span>
                            <input type="file" name="attachment" class="w-full text-sm">
                        </label>

                        <p class="text-xs rounded-xl p-2" style="background: var(--surface-sunken); color: var(--text-muted)">
                            اعتراض واحد لكلّ معاملة — وتقدر تضيف تفاصيل بعدها في صفحة الاعتراض.
                            وبيروح لمسؤولك المباشر.
                        </p>

                        <button type="submit" class="btn w-full rounded-xl px-4 py-2 text-sm font-semibold"
                                style="background: var(--color-brand-500); color: #04201c">إرسال الاعتراض</button>
                    </form>
                </x-modal>
            @endif
        @endforeach
    @endpush
@endsection

@section('mobile_action')
    <a href="{{ route('volunteer.transactions.export', ['days' => $filters['days']]) }}"
       class="btn w-full inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold"
       style="background: var(--color-brand-500); color: #04201c">تصدير كشف</a>
@endsection
