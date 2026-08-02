@php
    /**
     * سايد بار لوحة التطوّع (الدستور 13.4-ح) — ثلاثة عشر عنصرًا بالترتيب المعتمَد.
     *
     * قاعدتان حاكمتان:
     *  1) الظاهر يتحدّد بـ**صلاحيّات العضويّة النشطة** — وما لا يملكه المستخدم **يُخفى لا يُعطَّل** (2.15-أ-7).
     *  2) لا سلطة عابرة للكيانات: كلّ تقييم يمرّ بالمحرّك داخل العضويّة النشطة (12.2.1).
     */
    $u = auth()->user();
    $membership = $activeMembership ?? $u->activeMembership();

    /** يقرأ الصلاحيّة داخل العضويّة النشطة */
    $may = fn (string $permission) => $u->allows($permission);

    /** يبني عناصر المجموعة ويحذف المحظور تمامًا */
    $items = function (array $rows) use ($may) {
        return collect($rows)
            ->filter(fn ($row) => ! isset($row['can']) || $may($row['can']))
            ->map(fn ($row) => ['label' => $row['label'], 'route' => $row['route']])
            ->values()
            ->all();
    };

    $overviewItems = $items([
        ['label' => 'رحلتي في التطوّع', 'route' => 'volunteer.overview', 'can' => 'personal_reports.view'],
        ['label' => 'تقريري الأسبوعيّ', 'route' => 'volunteer.report', 'can' => 'personal_reports.view'],
        ['label' => 'تقويم نشاطي', 'route' => 'volunteer.calendar', 'can' => 'calendar.view'],
    ]);

    $taskItems = $items([
        ['label' => 'مهامّي', 'route' => 'volunteer.tasks.index', 'can' => 'tasks.list'],
        ['label' => 'لوحة المهام العامّة', 'route' => 'volunteer.tasks.board', 'can' => 'public_board.list'],
        ['label' => 'مساهماتي', 'route' => 'volunteer.contributions', 'can' => 'contributions.list'],
        ['label' => 'بانتظار مراجعتي', 'route' => 'volunteer.reviews', 'can' => 'tasks.approve'],
    ]);

    $volunteerUnread = $u->notificationsFeed()->whereNull('read_at')->where('layer', 'volunteer')->count();
@endphp

{{-- لوحة منزلقة على الموبايل وعمود ثابت على الديسكتوب (13 · 2.15-ج) --}}
<aside data-sidebar data-open="false" class="w-64 shrink-0" style="border-inline-start: 1px solid var(--border)">
    <div class="sticky top-0 h-screen overflow-y-auto p-4 space-y-4">

        {{-- بطاقة هويّة مصغّرة: الاسم والكود والكيان والبوزشن + شارة Rep الثابتة (13.4-ح) --}}
        <div class="card p-3 flex items-center gap-3">
            <x-avatar :user="$u" size="10" />
            <div class="min-w-0">
                <div class="truncate font-semibold text-sm flex items-center gap-1">
                    <span class="truncate">{{ $u->shortName() }}</span>
                    @include('volunteer.components.rep-badge', ['user' => $u])
                </div>
                <div class="text-xs truncate" style="color: var(--text-muted)">
                    {{ $membership?->entity?->name_ar ?? 'بلا كيان' }} · {{ $membership?->position?->name_ar ?? '—' }}
                </div>
            </div>
        </div>

        <nav class="space-y-1">
            {{-- 1) نظرة عامّة --}}
            @if ($overviewItems)
                <x-nav-group label="نظرة عامّة" icon="🏠" :items="$overviewItems" />
            @endif

            {{-- 2) المهام --}}
            @if ($taskItems)
                <x-nav-group label="المهام" icon="✅" :items="$taskItems" />
            @endif

            {{-- 3) المشاريع والأهداف --}}
            @can('goals.list')
                <x-nav-link route="volunteer.goals" label="المشاريع والأهداف" icon="🎯" />
            @endcan

            {{-- إطلاق الهدف (23 — 1.5): لصاحب الضغطة وحده، ومخفيّ عن غيره لا معطَّلًا --}}
            @can('goals.approve')
                <x-nav-link route="volunteer.goals.launch" label="إطلاق الهدف" icon="🚀" />
            @endcan

            {{-- 4) الأداء --}}
            @can('leaderboards.view')
                <x-nav-link route="volunteer.performance.vxp" label="الأداء" icon="📈" />
            @endcan

            {{-- 5) الاجتماعات --}}
            @can('meetings.list')
                <x-nav-link route="volunteer.meetings" label="الاجتماعات" icon="🗓️" />
            @endcan

            {{-- 6) المعاملات --}}
            @can('rep_transactions.list')
                <x-nav-link route="volunteer.transactions" label="المعاملات" icon="💳" />
            @endcan

            {{-- 7) قسمي --}}
            @can('departments.view')
                <x-nav-link route="volunteer.department" label="قسمي" icon="🏛️" />
            @endcan

            {{-- 8) التصعيدات — لمن تحته أعضاء --}}
            @can('escalations.list')
                <x-nav-link route="volunteer.escalations" label="التصعيدات" icon="⬆️" />
            @endcan

            {{-- 9) الأكاديمية --}}
            @can('academy_paths.list')
                <x-nav-link route="volunteer.academy" label="الأكاديمية" icon="🎓" />
            @endcan

            {{-- 10) المكتبة الداخليّة --}}
            @can('internal_library.list')
                <x-nav-link route="volunteer.library" label="المكتبة الداخليّة" icon="📚" />
            @endcan

            {{-- 11) التقدير --}}
            @can('kudos.view')
                <x-nav-link route="volunteer.kudos" label="التقدير" icon="💛" />
            @endcan

            {{-- 12) التوظيف — لفريق التوظيف --}}
            @can('candidates.list')
                <x-nav-link route="volunteer.recruitment" label="التوظيف" icon="🧑‍💼" />
            @endcan

            {{-- 13) إشعارات التطوّع — شخصيّة، وتظهر أيضًا كتاب في جرس الهيدر (2.8) --}}
            <x-nav-link route="volunteer.notifications" label="إشعارات التطوّع" icon="🔔" :badge="$volunteerUnread" />
        </nav>

        {{-- الرجوع لطبقة المتدرّب — بابٌ واحد واضح لا قائمة --}}
        <a href="{{ \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : '/' }}"
           class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm motion-standard"
           style="color: var(--text-muted)">
            <span class="w-5 text-center" aria-hidden="true">‹</span>
            <span>رجوع للرئيسيّة</span>
        </a>
    </div>
</aside>

{{-- الموبايل: نفس القائمة تنزلق من زرّ الهيدر (13 · 2.15-ج) --}}
@include('partials.sidebar-drawer')
