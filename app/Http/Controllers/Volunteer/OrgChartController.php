<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Services\Volunteer\Org\DepartmentScope;
use App\Services\Volunteer\Org\MemberDirectory;
use App\Services\Volunteer\Org\OrgChartBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الهيكل التنظيميّ (كانفاس) — 13.4-م-3 · 24.4-7.
 *
 * الخادم يعطي العقد والروابط والعدّادات، و**كلّ الرسم SVG بأيدينا**:
 * سحب وتكبير وخطوط منحنية تتحرّك مع السحب — **بلا أيّ مكتبة رسم خارجيّة** (2.16-ج).
 * وعلى الموبايل: **قائمة شجريّة قابلة للطيّ** بدل السحب (2.15-ج).
 */
class OrgChartController extends Controller
{
    public function __construct(
        private readonly DepartmentScope $scope,
        private readonly OrgChartBuilder $builder,
        private readonly MemberDirectory $directory,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $root = $this->scope->rootFor($user, (int) $request->query('entity') ?: null);

        if (! $root) {
            return view('volunteer.org.chart', [
                'root' => null,
                'roots' => collect(),
                'chart' => ['nodes' => [], 'me' => null, 'network_total' => 0, 'entity_members' => 0,
                    'collapse_threshold' => 0, 'default_depth' => 0, 'entity_name' => ''],
                'tree' => [],
                'snapshotDate' => now()->translatedFormat((string) setting('volunteer.org.date_format', 'j F Y')),
            ]);
        }

        $memberships = $this->scope->memberships($this->scope->entityIds($root));
        $chart = $this->builder->build($root, $memberships, $user);

        return view('volunteer.org.chart', [
            'root' => $root,
            'roots' => $this->scope->rootsFor($user),
            'chart' => $chart,
            'tree' => $this->builder->tree($chart['nodes']),
            'snapshotDate' => now()->translatedFormat((string) setting('volunteer.org.date_format', 'j F Y')),
        ]);
    }

    /**
     * ضغطة عقدة ⟵ بوب-أب تفاصيل سريعة + «فتح البروفايل» (2.10.1-17).
     *
     * ⭐ **النطاق يحسمه المحرّك لا قاعدة كيانٍ خاصّة هنا (12.2.1-ب):** كان الحارس
     * يمرّ بلا هدف، فيسدّ الكنترولر الفراغ بقاعدته هو — «هل العقدة داخل قسم
     * المشاهِد؟» — فانقلب الحكم في الاتّجاهين: صاحب `@SELF` يفتح عقدةً ليست له
     * لأنّها في قسمه، وصاحب `@ALL` يُردّ عن عقدةٍ **يغطّيها نطاقُه** لأنّها خارج
     * قسمه هو. الآن يقرّر `EnsurePermission` بـ`org_chart.view` على **هذه العقدة**،
     * ويبقى القسم هنا **مادّةَ العرض** (سلسلة الأبلاين والعدّادات) لا بوّابةً ثانية.
     */
    public function node(Request $request, Membership $membership): JsonResponse
    {
        $viewer = $request->user();
        $root = $membership->entity ? $this->scope->rootOf($membership->entity) : null;

        // 404 لا 403: مَن أذن له المحرّك ووصل هنا، غيابُ الكيان عطبُ بيانات لا منع
        abort_unless($root, 404);

        $pool = $this->scope->memberships($this->scope->entityIds($root));

        abort_unless($pool->has($membership->id), 404);

        return response()->json($this->directory->profile($membership, $viewer, $pool));
    }
}
