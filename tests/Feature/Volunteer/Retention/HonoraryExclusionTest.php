<?php

namespace Tests\Feature\Volunteer\Retention;

use App\Models\Membership;
use App\Services\Admin\Volunteer\CertificateEligibility;
use App\Services\Volunteer\Goals\ChampionService;
use App\Services\Volunteer\Goals\VxpDistributionService;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Facades\Cache;

/**
 * العنصر الشرفيّ «أخوكم» (13.4-ص-ج) — الفقرة منصوصة بالحصر:
 * «**لا يُحتسَب** في: عدّاد أعضاء الكيان · نطاقات الإشراف والسعة · صحّة القسم ·
 * **الليدر بورد ومشرف الشهر** · تقييمات مؤشّر القيادة · شبكة الداونلاين»
 * و«**لا يُصدَر له شهادة بوزشن تطوّع**».
 *
 * اختبار جامع واحد لثلاثة مواضع كانت مثبَتةً بالتشغيل: الليدر بورد ·
 * مشرف الشهر · أهليّة الشهادة.
 */
class HonoraryExclusionTest extends RetentionTestCase
{
    public function test_honorary_member_is_excluded_from_leaderboard_champion_and_certificate(): void
    {
        $entity = $this->makeEntity('قسم الإعلام');
        $ledger = app(LedgerService::class);

        // متطوّع عاديّ يعمل ويكسب
        $volunteer = $this->makeUser('نور المتطوّعة');
        $membership = $this->makeMembership($volunteer, $entity);
        $ledger->credit($volunteer, VxpDistributionService::CURRENCY, 120, 'task', null, 'volunteer');
        $ledger->credit($volunteer, LedgerService::REP, 3, 'task', null, 'volunteer');

        // «أخوكم»: مقعد تقديريّ أعلى الشجرة — وله رصيد أعلى من الجميع عمدًا
        $honorary = $this->makeUser('أخوكم');
        $honoraryMembership = Membership::create([
            'user_id' => $honorary->id,
            'entity_id' => $entity->id,
            'position_id' => $this->honoraryPosition()->id,
            'is_primary' => true,
            'started_at' => now()->subYear(),
            'status' => 'active',
        ]);
        $ledger->credit($honorary, VxpDistributionService::CURRENCY, 9999, 'task', null, 'volunteer');
        $ledger->credit($honorary, LedgerService::REP, 10, 'task', null, 'volunteer');

        // ------------------------------------------------------ 1) الليدر بورد
        $this->grant($volunteer, 'vxp_transactions.view', 'ALL', $membership);
        $this->grant($volunteer, 'leaderboards.view', 'ALL', $membership);

        $rows = collect($this->actingAs($volunteer)
            ->get(route('volunteer.performance.vxp'))
            ->assertOk()
            ->viewData('rows'));

        $this->assertTrue(
            $rows->contains(fn (array $row) => (int) $row['user']?->id === $volunteer->id),
            'المتطوّع العاديّ لازم يظهر في الليدر بورد'
        );
        $this->assertFalse(
            $rows->contains(fn (array $row) => (int) $row['user']?->id === $honorary->id),
            '«أخوكم» لا يُحتسَب في الليدر بورد (13.4-ص-ج)'
        );

        // ------------------------------------------------------ 2) مشرف الشهر
        Cache::flush();
        $board = app(ChampionService::class)->board($entity->id);
        $candidateIds = collect($board['candidates'])->map(fn (array $row) => (int) $row['user']->id);

        $this->assertContains($volunteer->id, $candidateIds->all());
        $this->assertNotContains($honorary->id, $candidateIds->all(), '«أخوكم» خارج مشرف الشهر (13.4-ص-ج)');
        $this->assertNotSame($honorary->id, (int) ($board['winner']['user']->id ?? 0));

        // ------------------------------------------------- 3) أهليّة الشهادة
        $honoraryMembership->forceFill(['started_at' => now()->subYear()])->save();

        $check = CertificateEligibility::check($honoraryMembership->fresh());
        $this->assertFalse($check['eligible'], 'لا شهادة بوزشن تطوّع للعنصر الشرفيّ (13.4-ص-ج)');

        // والمتطوّع العاديّ المستوفي يبقى مستحقًّا — الاستبعاد للشرفيّ وحده
        $membership->forceFill(['started_at' => now()->subYear()])->save();
        $this->assertTrue(CertificateEligibility::check($membership->fresh())['eligible']);

        // وقائمة «مستحقّ ولم تُصدَر» لا تحمله أصلًا
        $pendingIds = CertificateEligibility::pending()
            ->map(fn (array $row) => (int) $row['membership']->user_id)->all();

        $this->assertNotContains($honorary->id, $pendingIds);
    }
}
