@php
    /**
     * سايد بار لوحة التطوّع (الدستور 13.4-ح · 12.0) — ثلاثة عشر عنصرًا بالترتيب المعتمَد،
     * **أحدَ عشرَ منها مجموعةٌ بدروب-داون** واثنان رابطان مفردان.
     *
     * قواعد حاكمة:
     *  1) الظاهر يتحدّد بـ**صلاحيّات العضويّة النشطة** — وما لا يملكه المستخدم **يُخفى لا يُعطَّل** (2.15-أ-7).
     *  2) لا سلطة عابرة للكيانات: كلّ تقييم يمرّ بالمحرّك داخل العضويّة النشطة (12.2.1).
     *  3) ⭐ **ولا شاشة يتيمة:** كلّ شاشةٍ منصوصةٍ في 13.4-ح لها سطرٌ هنا. قبل ذلك كانت
     *     إحدى عشرة مجموعةً مطويّةً في **رابطٍ مفرد**، فبقيت شاشاتها الداخليّة لا يصلها
     *     المستخدم إلّا بكتابة الرابط بيده — ميزةٌ مبنيّةٌ لا تصل صاحبها.
     *
     * ومفاتيح كلّ سطر هي **نفس مفاتيح مساره** حرفًا بحرف، فلا يظهر سطرٌ يقود إلى 403
     * ولا يُخفى سطرٌ يملكه صاحبه.
     */
    $u = auth()->user();
    $membership = $activeMembership ?? $u->activeMembership();

    /** يملك أيًّا من المفاتيح (المسار نفسه يقبل أيًّا منها) */
    $mayAny = fn (array $keys) => collect($keys)->contains(fn (string $key) => $u->allows($key));

    /** يبني عناصر المجموعة ويحذف المحظور تمامًا — ويسقط ما لا مسار له بعد */
    $items = function (array $rows) use ($mayAny) {
        return collect($rows)
            ->filter(fn ($row) => \Illuminate\Support\Facades\Route::has($row['route']))
            ->filter(fn ($row) => ! isset($row['can']) || $mayAny((array) $row['can']))
            ->map(fn ($row) => ['label' => $row['label'], 'route' => $row['route']])
            ->values()
            ->all();
    };

    // 1) 🏠 نظرة عامّة
    $overviewItems = $items([
        ['label' => 'رحلتي في التطوّع', 'route' => 'volunteer.overview', 'can' => 'personal_reports.view'],
        ['label' => 'تقريري الأسبوعيّ', 'route' => 'volunteer.report', 'can' => 'personal_reports.view'],
        ['label' => 'تقويم نشاطي', 'route' => 'volunteer.calendar', 'can' => 'calendar.view'],
    ]);

    // 2) ✅ المهام
    $taskItems = $items([
        ['label' => 'مهامّي', 'route' => 'volunteer.tasks.index', 'can' => 'tasks.list'],
        ['label' => 'لوحة المهام العامّة', 'route' => 'volunteer.tasks.board', 'can' => 'public_board.list'],
        ['label' => 'مساهماتي', 'route' => 'volunteer.contributions', 'can' => 'contributions.list'],
        ['label' => 'بانتظار مراجعتي', 'route' => 'volunteer.reviews', 'can' => ['tasks.approve', 'contributions.approve']],
    ]);

    // 3) 🎯 المشاريع والأهداف — الأهداف والمَعالِم · حزم العمل وبنودها · المشروع التشغيليّ · البنود المتكرّرة
    $goalItems = $items([
        ['label' => 'الأهداف والمَعالِم', 'route' => 'volunteer.goals', 'can' => ['goals.list', 'goals.view']],
        ['label' => 'بناء الأهداف', 'route' => 'volunteer.goals.build', 'can' => ['goals.create', 'milestones.create']],
        ['label' => 'إطلاق الهدف', 'route' => 'volunteer.goals.launch', 'can' => 'goals.approve'],
        ['label' => 'حزم العمل وبنودها', 'route' => 'volunteer.packages', 'can' => ['work_packages.list', 'work_packages.view']],
        ['label' => 'المشروع التشغيليّ', 'route' => 'volunteer.project', 'can' => 'operational_projects.view'],
        ['label' => 'البنود المتكرّرة', 'route' => 'volunteer.recurring', 'can' => 'recurring_items.view'],
    ]);

    // 4) 📈 الأداء — VXP وترتيبي · درجة الالتزام (Rep) · مشرف الشهر · تقييماتي
    $performanceItems = $items([
        ['label' => 'VXP وترتيبي', 'route' => 'volunteer.performance.vxp', 'can' => 'leaderboards.view'],
        ['label' => 'درجة الالتزام (Rep)', 'route' => 'volunteer.performance.rep', 'can' => 'rep_transactions.view'],
        ['label' => 'مشرف الشهر', 'route' => 'volunteer.performance.champion', 'can' => 'leaderboards.view'],
        ['label' => 'تقييماتي (مؤشّر القيادة)', 'route' => 'volunteer.performance.evaluations', 'can' => 'evaluations.view'],
    ]);

    // 5) 🗓️ الاجتماعات — القادمة والمنتهية · حضوري · المحاضر والمرفقات
    $meetingItems = $items([
        ['label' => 'القادمة والمنتهية', 'route' => 'volunteer.meetings', 'can' => ['meetings.list', 'meetings.view']],
        ['label' => 'حضوري والمحاضر', 'route' => 'volunteer.attendance', 'can' => ['meeting_attendance.view', 'meeting_minutes.view', 'meetings.view']],
    ]);

    // 6) 💳 المعاملات — معاملاتي (Rep/VXP) · اعتراضاتي
    $transactionItems = $items([
        ['label' => 'معاملاتي (Rep/VXP)', 'route' => 'volunteer.transactions', 'can' => ['rep_transactions.list', 'rep_transactions.view', 'vxp_transactions.list']],
        ['label' => 'اعتراضاتي', 'route' => 'volunteer.objections', 'can' => ['objections.view', 'objections.list']],
    ]);

    // 7) 🏛️ قسمي — الأعضاء والبوزشنز · الهيكل التنظيميّ · صحّة القسم · السعة والأحمال
    $departmentItems = $items([
        ['label' => 'الأعضاء والبوزشنز', 'route' => 'volunteer.department', 'can' => 'org_chart.view'],
        ['label' => 'الهيكل التنظيميّ', 'route' => 'volunteer.org', 'can' => 'org_chart.view'],
        ['label' => 'صحّة القسم', 'route' => 'volunteer.health', 'can' => 'team_health.view'],
        ['label' => 'السعة والأحمال', 'route' => 'volunteer.capacity', 'can' => 'capacity.view'],
    ]);

    /*
     | 8) ⬆️ التصعيدات — **تظهر لمن تحته أعضاء** (24.4-8):
     | يحتاج قرارك · **الاعتراضات المصعَّدة** · التحكيمات.
     | ومفتاح «الاعتراضات المصعَّدة» هو `objections.list` — وهو غير ممنوح
     | للكوردنيتور (لا نطاق SELF له في المصفوفة 12.2.2)، فمن لا داونلاين له
     | لا يرى السطر أصلًا.
     */
    $escalationItems = $items([
        ['label' => 'يحتاج قرارك', 'route' => 'volunteer.escalations', 'can' => 'escalations.list'],
        ['label' => 'الاعتراضات المصعَّدة', 'route' => 'volunteer.escalations.objections', 'can' => 'objections.list'],
        ['label' => 'التحكيمات', 'route' => 'volunteer.arbitrations', 'can' => 'arbitration.list'],
    ]);

    // 9) 🎓 الأكاديمية — التدريبات · التسجيلات
    $academyItems = $items([
        ['label' => 'التدريبات', 'route' => 'volunteer.academy', 'can' => ['academy_paths.list', 'academy_paths.view']],
        ['label' => 'التسجيلات', 'route' => 'volunteer.academy.recordings', 'can' => ['academy_recordings.list', 'academy_recordings.view']],
    ]);

    // 11) 💛 التقدير — Kudos · حائط الشكر (نادي +9.5)
    $recognitionItems = $items([
        ['label' => 'Kudos', 'route' => 'volunteer.kudos', 'can' => 'kudos.view'],
        ['label' => 'حائط الشكر (نادي +9.5)', 'route' => 'volunteer.kudos.wall', 'can' => 'thanks_wall.view'],
    ]);

    // 12) 🧑‍💼 التوظيف — المرشّحون (كانبان) · المقابلات والـScorecards · القوائم والتسكين
    $recruitmentItems = $items([
        ['label' => 'المرشّحون (كانبان)', 'route' => 'volunteer.recruitment', 'can' => 'candidates.list'],
        ['label' => 'المقابلات والـScorecards', 'route' => 'volunteer.interviews', 'can' => ['interviews.list', 'interviews.view']],
        ['label' => 'القوائم والتسكين', 'route' => 'volunteer.placement', 'can' => 'placements.list'],
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
            @if ($goalItems)
                <x-nav-group label="المشاريع والأهداف" icon="🎯" :items="$goalItems" />
            @endif

            {{-- 4) الأداء --}}
            @if ($performanceItems)
                <x-nav-group label="الأداء" icon="📈" :items="$performanceItems" />
            @endif

            {{-- 5) الاجتماعات --}}
            @if ($meetingItems)
                <x-nav-group label="الاجتماعات" icon="🗓️" :items="$meetingItems" />
            @endif

            {{-- 6) المعاملات --}}
            @if ($transactionItems)
                <x-nav-group label="المعاملات" icon="💳" :items="$transactionItems" />
            @endif

            {{-- 7) قسمي --}}
            @if ($departmentItems)
                <x-nav-group label="قسمي" icon="🏛️" :items="$departmentItems" />
            @endif

            {{-- 8) التصعيدات — لمن تحته أعضاء --}}
            @if ($escalationItems)
                <x-nav-group label="التصعيدات" icon="⬆️" :items="$escalationItems" />
            @endif

            {{-- 9) الأكاديمية --}}
            @if ($academyItems)
                <x-nav-group label="الأكاديمية" icon="🎓" :items="$academyItems" />
            @endif

            {{-- 10) المكتبة الداخليّة — رابط مفرد بلا شاشات داخليّة --}}
            @can('internal_library.list')
                <x-nav-link route="volunteer.library" label="المكتبة الداخليّة" icon="📚" />
            @endcan

            {{-- 11) التقدير --}}
            @if ($recognitionItems)
                <x-nav-group label="التقدير" icon="💛" :items="$recognitionItems" />
            @endif

            {{-- 12) التوظيف — لفريق التوظيف --}}
            @if ($recruitmentItems)
                <x-nav-group label="التوظيف" icon="🧑‍💼" :items="$recruitmentItems" />
            @endif

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
