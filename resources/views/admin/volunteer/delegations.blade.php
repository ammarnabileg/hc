@extends('layouts.admin')

@section('title', 'الغيابات والتفويض')

@section('content')
    {{--
        ⭐ شاشة إدارة الغيابات (23-6 · 24).
        سؤالها الواحد: **«مَن الغائب، ومَن يقرّر مكانه، وإلى متى؟»**
        والإضافة ليست هنا: موضعها «الأعضاء والبوزشنز» عند مَن يعرف قسمه —
        فالفعل الرئيسيّ الوحيد في هذه الشاشة هو **الإنهاء المبكّر** (2.15-أ-2).
    --}}
    <x-page-header
        title="الغيابات والتفويض المؤقّت"
        subtitle="الغائب المعذور لا يُخصَم تباطؤًا — وقراراته تروح لبديله لحدّ ما يرجع."
        :breadcrumbs="[['label' => 'التطوّع', 'url' => route('admin.volunteer.index')], ['label' => 'الغيابات والتفويض']]" />

    @include('admin.volunteer.partials.tabs', ['current' => 'delegations'])

    {{-- القاعدة معلَنة قبل أيّ إجراء — فلا يُضيف أحدٌ من هنا ثم يسأل لماذا --}}
    <div class="card p-3 mb-4 text-sm space-y-1">
        <div>
            <x-icon name="info" size="16" />
            <strong>الإضافة من «الأعضاء والبوزشنز»</strong> — يفتحها مشرف عام التطوّع أو مشرف المسار أو دايركتور الكيان،
            <strong>ولا يفتحها الشخص لنفسه</strong> منعًا للتهرّب.
        </div>
        <div>
            <x-icon name="clock" size="16" />
            الحدود الحاليّة: أقصى غياب متّصل <strong>{{ $maxDays }}</strong> يومًا · وبحدّ <strong>{{ $maxPerMonth }}</strong> مرّات في الشهر.
        </div>
    </div>

    {{-- 4 كروت KPI بحدّ أقصى (2.15-أ-3) — وعلى الموبايل شبكة 2×2 --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-kpi label="غائبون الآن" :value="$kpis['current']" icon="user" />
        <x-kpi label="تنتهي قريبًا" :value="$kpis['ending_soon']" icon="hourglass" />
        <x-kpi :label="'تبدأ خلال '.$kpis['soon_days'].' يومًا'" :value="$kpis['upcoming']" icon="calendar" />
        <x-kpi label="بلا بديل نشِط" :value="$kpis['no_delegate']" icon="warning"
               hint="نافذة قرار بلا صاحب — راجعها فورًا" :state="$kpis['no_delegate'] > 0 ? 'danger' : 'ok'" />
    </div>

    {{-- 3 فلاتر ظاهرة (2.15-أ-4) --}}
    <x-filters :action="route('admin.volunteer.delegations')" screen="admin.delegations">
        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الحالة</span>
            <select name="state" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                @foreach ($states as $key => $label)
                    <option value="{{ $key }}" @selected($filters['state'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">الكيان</span>
            <select name="entity" onchange="this.form.submit()" class="rounded-xl px-3 py-2 text-sm"
                    style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                <option value="">الكلّ</option>
                @foreach ($entities as $entity)
                    <option value="{{ $entity->id }}" @selected((int) $filters['entity'] === (int) $entity->id)>{{ $entity->name_ar }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="block text-xs mb-1" style="color: var(--text-muted)">بحث بالاسم/الكود</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" class="rounded-xl px-3 py-2 text-sm"
                   style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
        </label>

        <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold" style="background: var(--surface-raised)">فلتر</button>
    </x-filters>

    {{-- كروت رأسيّة: بلا تمرير أفقيّ على الموبايل (2.15-ج) --}}
    <section class="space-y-3">
        @forelse ($rows as $row)
            @php
                $state = $row->state();
                $delegate = $row->delegate_membership?->user;
                $delegateActive = $row->delegate_membership && $row->delegate_membership->status === 'active';
            @endphp

            <article class="card p-4">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <div class="font-semibold">
                            {{ $row->membership?->user?->name ?? 'عضو محذوف' }}
                            <span class="text-xs" style="color: var(--text-muted)">#{{ $row->membership?->user?->code }}</span>
                        </div>
                        <div class="text-xs mt-0.5" style="color: var(--text-muted)">
                            {{ $row->membership?->position?->name_ar ?? '—' }} · {{ $row->membership?->entity?->name_ar ?? '—' }}
                        </div>
                    </div>

                    {{-- اللون لا يحمل المعنى وحده — شارة برمز ونصّ (2.16) --}}
                    <x-state-badge :state="$state === 'current' ? 'warn' : ($state === 'upcoming' ? 'idle' : 'ok')"
                                   :label="$states[$state]" />
                </div>

                <dl class="grid gap-2 sm:grid-cols-2 mt-3 text-sm">
                    <div>
                        <dt class="text-xs" style="color: var(--text-muted)">المدّة</dt>
                        <dd>
                            {{ $row->from_date?->format('Y-m-d') }} ← {{ $row->to_date?->format('Y-m-d') }}
                            <span class="text-xs" style="color: var(--text-muted)">({{ $row->effectiveDays() }} يومًا فعليًّا)</span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs" style="color: var(--text-muted)">البديل المفوَّض</dt>
                        <dd>
                            @if ($delegate && $delegateActive)
                                {{ $delegate->name }}
                                <span class="text-xs" style="color: var(--text-muted)">— {{ $row->delegate_membership?->position?->name_ar }}</span>
                            @else
                                <x-state-badge state="danger" label="بلا بديل نشِط — القرارات معلّقة" />
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs" style="color: var(--text-muted)">السبب</dt>
                        <dd>{{ $row->reason ?: '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs" style="color: var(--text-muted)">سجّله</dt>
                        <dd>{{ $row->created_by?->name ?? '—' }} · {{ $row->created_at?->format('Y-m-d') }}</dd>
                    </div>
                </dl>

                @if ($row->ended_at)
                    <p class="text-xs mt-3" style="color: var(--text-muted)">
                        <x-icon name="check" size="16" />
                        اتقفل مبكّرًا يوم {{ $row->ended_at->format('Y-m-d') }} بواسطة {{ $row->ended_by?->name ?? '—' }}
                        @if ($row->ended_note) — {{ $row->ended_note }} @endif
                    </p>
                @elseif ($state !== 'ended')
                    {{-- المحظور يُخفى لا يُعطَّل (2.15-أ-7) --}}
                    @can('delegations.edit')
                        <details class="mt-3">
                            <summary class="cursor-pointer text-sm font-semibold select-none">رجع قبل ميعاده؟ أنهِ الغياب</summary>
                            <form method="post" action="{{ route('admin.volunteer.delegations.end', $row) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                @csrf
                                <label class="text-sm grow">
                                    <span class="block text-xs mb-1" style="color: var(--text-muted)">سبب الإنهاء (إلزاميّ)</span>
                                    <input type="text" name="note" required minlength="3" maxlength="300"
                                           class="w-full rounded-xl px-3 py-2 text-sm"
                                           style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)">
                                </label>
                                <button type="submit" class="btn rounded-xl px-4 py-2 text-sm font-semibold"
                                        style="background: var(--color-brand-500); color: #04201c">أنهِ الغياب</button>
                            </form>
                            <p class="text-xs mt-2" style="color: var(--text-muted)">
                                هترجع له قراراته فورًا، وساعات مهامّه هتتزاح بمدّة غيابه الفعليّة — لا بالمدّة المعلَنة.
                            </p>
                        </details>
                    @endcan
                @endif
            </article>
        @empty
            <x-empty message="مفيش غيابات في الحالة دي — الفريق كامل." />
        @endforelse
    </section>

    {{-- سجلّ التدقيق: مَن أنهى غيابًا ومتى ولماذا (24 — Audit على كلّ إجراء) --}}
    <details class="card p-4 mt-5">
        <summary class="cursor-pointer text-sm font-semibold select-none">سجلّ التدقيق</summary>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($audit as $entry)
                <li class="flex flex-wrap items-baseline gap-2">
                    <span class="text-xs" style="color: var(--text-muted)">{{ $entry->created_at?->format('Y-m-d H:i') }}</span>
                    <span>{{ $entry->user?->name ?? 'النظام' }} أنهى غيابًا مبكّرًا</span>
                    @if ($entry->new_values['note'] ?? null)
                        <span class="text-xs" style="color: var(--text-muted)">— {{ $entry->new_values['note'] }}</span>
                    @endif
                </li>
            @empty
                <li class="text-sm" style="color: var(--text-muted)">لسّه مفيش إنهاء مبكّر مسجَّل.</li>
            @endforelse
        </ul>
    </details>
@endsection
