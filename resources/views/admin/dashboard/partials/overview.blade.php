{{-- نظرة عامّة: أربعة كروت KPI بالحدّ الأقصى، ثمّ القرارات المستنّية، ثمّ السجلّ (12.3 · 2.15-أ-3) --}}

{{-- على الموبايل: صفّ متمرّر أفقيًّا بدل أربعة مضغوطة (2.15-ج) --}}
<div class="flex gap-3 overflow-x-auto no-scrollbar pb-1 sm:grid sm:grid-cols-2 lg:grid-cols-4 sm:overflow-visible">
    @foreach ($kpis as $card)
        @include('admin.dashboard.components.kpi', ['card' => $card, 'compare' => $compare])
    @endforeach
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-2">
    @include('admin.dashboard.components.chart-lines', ['points' => $series])

    {{-- حسابات محتاجة موافقة + [عرض الكلّ] (12.3) --}}
    <section class="card p-4 min-w-0">
        <div class="flex items-baseline justify-between gap-2">
            <h3 class="font-bold text-sm">حسابات محتاجة موافقة</h3>
            @if ($pendingAccountsCount > 0 && auth()->user()->allows('user_approvals.list'))
                <a href="{{ route('admin.users.approvals') }}" class="text-xs hover:underline"
                   style="color: var(--color-brand-500)">عرض الكلّ ({{ $pendingAccountsCount }})</a>
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
        <h3 class="font-bold text-sm">مهامّ معلّقة</h3>
        <div class="flex items-center gap-2 text-xs" style="color: var(--text-muted)">
            @foreach ($pendingWorkCounts as $type => $count)
                <span>{{ $type }}: {{ $count }}</span>
            @endforeach
        </div>
    </div>

    @if ($pendingWork->isEmpty())
        <p class="mt-4 text-sm" style="color: var(--text-muted)">مفيش حاجة مستنّية — استريّح شويّة.</p>
    @else
        {{-- الجدول على الموبايل كروت رأسيّة بلا تمرير أفقيّ (2.15-ج) --}}
        <ul class="mt-3 space-y-2 md:space-y-0 md:divide-y" style="border-color: var(--border)">
            @foreach ($pendingWork as $row)
                <li class="card p-3 md:flex md:items-center md:gap-3" style="border-color: var(--border)">
                    <span class="text-sm font-semibold md:w-24">{{ $row['icon'] }} {{ $row['type'] }}</span>
                    <span class="block md:flex-1 md:min-w-0 md:truncate text-sm mt-1 md:mt-0">{{ $row['title'] }}</span>

                    <span class="inline-flex items-center gap-2 mt-2 md:mt-0">
                        <x-state-badge :state="$row['state']" :label="$row['at']?->diffForHumans()" />
                        @if ($row['url'])
                            <a href="{{ $row['url'] }}" class="text-xs hover:underline" style="color: var(--color-brand-500)">مراجعة</a>
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
            <h3 class="font-bold text-sm">آخر النشاطات</h3>

            <form method="get" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="days" value="{{ $days }}">

                <select name="actor" onchange="this.form.submit()" class="rounded-xl px-2 py-1 text-xs"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                        aria-label="فلتر الموظّف">
                    <option value="">كلّ الموظّفين</option>
                    @foreach ($activityActors as $actor)
                        <option value="{{ $actor->id }}" @selected(request('actor') == $actor->id)>{{ $actor->name }}</option>
                    @endforeach
                </select>

                <select name="action" onchange="this.form.submit()" class="rounded-xl px-2 py-1 text-xs"
                        style="background: var(--surface-sunken); border: 1px solid var(--border); color: var(--text)"
                        aria-label="فلتر النوع">
                    <option value="">كلّ الأنواع</option>
                    @foreach (\App\Services\Admin\AuditTrail::ACTIONS as $key => $label)
                        <option value="{{ $key }}" @selected(request('action') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($activity->isEmpty())
            <p class="mt-4 text-sm" style="color: var(--text-muted)">مافيش نشاط في الفلتر ده.</p>
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
