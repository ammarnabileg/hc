<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\Permission;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ **شاشات التطوّع اليتيمة** (الدستور 13.4-ح · 12.0 · 2.15-أ-7).
 *
 * خريطة السايد بار المعتمَدة **ثلاثة عشر عنصرًا، أحدَ عشرَ منها بدروب-داون**.
 * وكان المبنيّ رابطًا مفردًا لكلّ مجموعة، فبقيت شاشاتها الداخليّة **بلا أيّ
 * رابطٍ وارد في المستودع كلّه** — لا يصلها المستخدم إلّا بكتابة الرابط بيده.
 *
 * هنا نثبت أمرين معًا: أنّ لكلّ شاشةٍ منصوصةٍ مدخلًا، وأنّ المدخل **يُخفى**
 * عمّن لا يملك مفتاحه — فالإخفاء بالصلاحيّة لا بالحذف.
 */
class VolunteerSidebarOrphanScreensTest extends FlowTestCase
{
    /** الشاشات التي كانت يتيمة: المسار ⟵ مفتاح المصفوفة الذي يفتحه */
    private const ORPHANS = [
        'volunteer/packages' => 'work_packages.list',
        'volunteer/project' => 'operational_projects.view',
        'volunteer/recurring' => 'recurring_items.view',
        'volunteer/performance/rep' => 'rep_transactions.view',
        'volunteer/performance/champion' => 'leaderboards.view',
        'volunteer/performance/evaluations' => 'evaluations.view',
        'volunteer/attendance' => 'meeting_attendance.view',
        'volunteer/objections' => 'objections.view',
        'volunteer/org' => 'org_chart.view',
        'volunteer/health' => 'team_health.view',
        'volunteer/capacity' => 'capacity.view',
        'volunteer/arbitrations' => 'arbitration.list',
        'volunteer/academy/recordings' => 'academy_recordings.list',
        'volunteer/kudos/wall' => 'thanks_wall.view',
        'volunteer/interviews' => 'interviews.list',
        'volunteer/placement' => 'placements.list',
        // ⬆️ والشاشة المبنيّة في هذه الجولة: الاعتراضات المصعَّدة إليّ (24.4-8)
        'volunteer/escalations/objections' => 'objections.list',
    ];

    /** يمنح المفاتيح بنطاق ALL — فما يُخفى بعدها يكون مخفيًّا بسببٍ آخر لا بالنطاق */
    private function grantKeys(User $user, string ...$keys): void
    {
        foreach ($keys as $key) {
            [$resource, $action] = explode('.', $key);

            $permission = Permission::query()->firstOrCreate(['key' => $key], [
                'resource' => $resource,
                'action' => $action,
                'group' => 'التطوّع',
                'label_ar' => $key,
                'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
            ]);

            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget($user);
    }

    /** السايد بار كما يراه هذا المستخدم فعلًا (من شاشة تمتدّ `layouts.volunteer`) */
    private function sidebar(User $user): string
    {
        return $this->actingAs($user)
            ->get(route('volunteer.escalations'))
            ->assertOk()
            ->getContent();
    }

    /** ⭐ كلّ شاشة كانت يتيمة صار لها رابطٌ في السايد بار */
    public function test_every_orphan_screen_now_has_a_sidebar_entry(): void
    {
        $this->grantKeys($this->top, 'escalations.list', ...array_values(self::ORPHANS));

        $html = $this->sidebar($this->top);

        foreach (array_keys(self::ORPHANS) as $uri) {
            $this->assertStringContainsString($uri, $html, "شاشة يتيمة بلا مدخل: {$uri}");
        }
    }

    /** والخريطة نفسها: إحدى عشرة مجموعةً بدروب-داون (13.4-ح) */
    public function test_the_map_renders_eleven_dropdown_groups(): void
    {
        $this->grantKeys(
            $this->top,
            'escalations.list', 'personal_reports.view', 'calendar.view', 'tasks.list',
            'public_board.list', 'contributions.list', 'tasks.approve', 'goals.list',
            'meetings.list', 'rep_transactions.list', 'kudos.view', 'candidates.list',
            'academy_paths.list',
            ...array_values(self::ORPHANS),
        );

        $html = $this->sidebar($this->top);

        foreach ([
            'نظرة عامّة', 'المهام', 'المشاريع والأهداف', 'الأداء', 'الاجتماعات',
            'المعاملات', 'قسمي', 'التصعيدات', 'الأكاديمية', 'التقدير', 'التوظيف',
        ] as $group) {
            $this->assertStringContainsString($group, $html, "مجموعة ناقصة من الخريطة: {$group}");
        }
    }

    /** ⭐ وما لا يملكه المستخدم **يُخفى لا يُعطَّل** (2.15-أ-7) */
    public function test_entries_are_hidden_from_whoever_lacks_their_key(): void
    {
        // مفتاح واحد فقط: باب شاشة «يحتاج قرارك» — ولا شيء غيره
        $this->grantKeys($this->top, 'escalations.list');

        $html = $this->sidebar($this->top);

        foreach (array_keys(self::ORPHANS) as $uri) {
            $this->assertStringNotContainsString($uri, $html, "سطر ظهر لمن لا يملك مفتاحه: {$uri}");
        }
    }

    /** ومَن يملك مفتاحًا بعينه يرى سطره وحده */
    public function test_a_single_key_opens_its_own_line_only(): void
    {
        $this->grantKeys($this->top, 'escalations.list', 'objections.list');

        $html = $this->sidebar($this->top);

        $this->assertStringContainsString('volunteer/escalations/objections', $html);
        $this->assertStringContainsString('الاعتراضات المصعَّدة', $html);
        $this->assertStringNotContainsString('volunteer/arbitrations', $html);
        $this->assertStringNotContainsString('volunteer/capacity', $html);
    }
}
