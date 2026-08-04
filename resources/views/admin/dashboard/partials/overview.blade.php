{{-- نظرة عامّة: أربعة كروت KPI بالحدّ الأقصى، ثمّ القرارات المستنّية، ثمّ السجلّ (12.3 · 2.15-أ-3) --}}

{{-- على الموبايل: صفّ متمرّر أفقيًّا بدل أربعة مضغوطة (2.15-ج) --}}
<div class="flex gap-3 min-w-0 overflow-x-auto no-scrollbar pb-1 sm:grid sm:grid-cols-2 lg:grid-cols-4 sm:overflow-visible">
    @foreach ($kpis as $card)
        @include('admin.dashboard.components.kpi', ['card' => $card, 'compare' => $compare])
    @endforeach
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-2">
    @include('admin.dashboard.components.chart-lines', ['points' => $series])

    {{-- حسابات محتاجة موافقة + [عرض الكلّ] (12.3) --}}
    <section class="card p-4 min-w-0">
        <div class="flex items-baseline justify-between gap-2">
            <h3 class="font-bold text-sm">{{ setting('admin.dashboard.partials.overview.hsabat_mhtaja_mwafqa', 'حسابات محتاجة موافقة') }}</h3>
            @if ($pendingAccountsCount > 0 && auth()->user()->allows('user_approvals.list'))
                <a href="{{ route('admin.users.approvals') }}" class="text-xs hover:underline"
                   style="color: var(--color-brand-500)">{{ setting('admin.dashboard.partials.overview.ard_alkl', 'عرض الكلّ (') }}{{ $pendingAccountsCount }})</a>
            @endif
        </div>

        @if ($pendingAccounts->isEmpty())
            <p class="mt-4 text-sm" style="color: var(--text-muted)">{{ setting('admin.approvals.empty_message', 'مفيش طلبات معلّقة — كلّ حاجة تمام') }}</p>
        @else
            <ul class="mt-3 divide-y" style="border-color: var(--border)">
                @foreach ($pendingAccounts as $account)
                    <li class="flex items-center gap-3 py-2" style="border-color: var(--border)">
                        <x-avatar :user="$account" size="8" />
                        <span class="min-w-0 flex-1 truncate text-sm">{{ $account->shortName() }}</span>
                        <span class="text-xs cursor-help" style="color: var(--text-muted)"
                              title="{{ $account->created_at?->format('Y-m-d H:i') }}">
                            {{ $account->created_at?->diffForHumans() }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

{{-- المهامّ المعلّقة: سحوبات وشكاوى — الأقدم أوّلًا وكلّ بند بزرّ [مراجعة] (12.3-16) --}}
<section class="card p-4 mt-6">
    <div class="flex items-baseline justify-between gap-2 flex-wrap">
        <h3 class="font-bold text-sm">{{ setting('admin.dashboard.partials.overview.mham_malqa', 'مهامّ معلّقة') }}</h3>
        <div class="flex items-center gap-2 text-xs" style="color: var(--text-muted)">
            @foreach ($pendingWorkCounts as $type => $count)
                <span>{{ $type }}: {{ $count }}</span>
            @endforeach
        </div>
    </div>

    @if ($pendingWork->isEmpty())
        <p class="mt-4 text-sm" style="color: var(--text-muted)">{{ setting('admin.dashboard.partials.overview.mfysh_haja_mstnya_astryh_shwya', 'مفيش حاجة مستنّية — استريّح شويّة.') }}</p>
    @else
        {{-- الجدول على الموبايل كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
        <ul class="mt-3 space-y-2 md:space-y-0 md:divide-y" style="border-color: var(--border)">
            @foreach ($pendingWork as $row)
                <li class="card p-3 md:flex md:items-center md:gap-3" style="border-color: var(--border)">
                    <span class="text-sm font-semibold md:w-24 inline-flex items-center gap-1">
                        <x-icon :name="$row['icon']" size="16" />{{ $row['type'] }}
                    </span>
                    <span class="block md:flex-1 md:min-w-0 md:truncate text-sm mt-1 md:mt-0">{{ $row['title'] }}</span>

                    <span class="inline-flex items-center gap-2 mt-2 md:mt-0">
                        <x-state-badge :state="$row['state']" :label="$row['at']?->diffForHumans()" />
                        @if ($row['url'])
                            <a href="{{ $row['url'] }}" class="text-xs hover:underline" style="color: var(--color-brand-500)">{{ setting('admin.dashboard.partials.overview.mrajaa', 'مراجعة') }}</a>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</section>

{{-- سجلّ النشاطات مفلتَر — للشفافيّة والحوكمة (12.3-20)، ولمن له صلاحيّته وحده --}}
@if ($canSeeActivity)
    <section class="card p-4 mt-6">
        <div class="flex items-baseline justify-between gap-2 flex-wrap">
            <h3 class="font-bold text-sm">{{ setting('admin.dashboard.partials.overview.akhr_alnshatat', 'آخر النشاطات') }}</h3>

            {{-- ⭐ تصدير السجلّ (12.3-20) — بصلاحيّته المستقلّة، ويحمل نفس فلاتر الشاشة --}}
            @if ($canExportActivity)
                <a href="{{ route('admin.dashboard.activity.export', array_filter([
                        'from' => $period['from']->toDateString(),
                        'to' => $period['to']->toDateString(),
                        'actor' => request('actor'),
                        'action' => request('action'),
                   ])) }}"
                   class="text-xs rounded-xl px-3 py-2 motion-standard"
                   style="background: var(--surface-sunken); color: var(--text); min-height: 44px; display: inline-flex; align-items: center; gap: .35rem">
                    <x-icon name="download" size="14" />
                    <span>{{ setting('admin.dashboard.activity_export_label', 'تصدير السجلّ') }}</span>
                </a>
            @endif

            <form method="get" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="from" value="{{ $period['from']->toDateString() }}">
                <input type="hidden" name="to" value="{{ $period['to']->toDateString() }}">
                @if ($compare)
                    <input type="hidden" name="compare" value="1">
                @endif

                <select name="actor" onchange="this.form.submit()" class="rounded-xl px-2 py-1 text-xs"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                        aria-label="{{ setting('admin.dashboard.partials.overview.fltr_almwzf', 'فلتر الموظّف') }}">
                    <option value="">{{ setting('admin.dashboard.partials.overview.kl_almwzfyn', 'كلّ الموظّفين') }}</option>
                    @foreach ($activityActors as $actor)
                        <option value="{{ $actor->id }}" @selected(request('actor') == $actor->id)>{{ $actor->name }}</option>
                    @endforeach
                </select>

                <select name="action" onchange="this.form.submit()" class="rounded-xl px-2 py-1 text-xs"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                        aria-label="{{ setting('admin.dashboard.partials.overview.fltr_alnwa', 'فلتر النوع') }}">
                    <option value="">{{ setting('admin.dashboard.partials.overview.kl_alanwaa', 'كلّ الأنواع') }}</option>
                    @foreach (\App\Services\Admin\AuditTrail::ACTIONS as $key => $label)
                        <option value="{{ $key }}" @selected(request('action') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($activity->isEmpty())
            {{-- الحالة الفارغة سطر واحد + زرّ واحد: «غيّر الفترة» (2.15-د · 24.1) --}}
            <div class="mt-3">
                <x-empty :message="setting('admin.dashboard.empty_message', 'مفيش بيانات في الفترة دي — وسّع المدى')"
                         :action="setting('admin.dashboard.partials.overview.ghyr_alftra', 'غيّر الفترة')" :href="route('admin.dashboard')" />
            </div>
        @else
            <ul class="mt-3 divide-y" style="border-color: var(--border)">
                @foreach ($activity as $log)
                    <li class="flex flex-wrap items-center gap-2 py-2 text-sm" style="border-color: var(--border)">
                        <span class="font-semibold">{{ \App\Services\Admin\AuditTrail::label($log->action) }}</span>
                        @if ($log->user)
                            <a href="{{ $log->user->profileUrl() }}" class="text-xs hover:underline"
                               style="color: var(--color-brand-500)">{{ $log->user->shortName() }}</a>
                        @endif
                        <span class="text-xs cursor-help" style="color: var(--text-muted)"
                              title="{{ $log->created_at?->format('Y-m-d H:i') }}">{{ $log->created_at?->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
