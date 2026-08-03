<?php

namespace Tests\Feature\Volunteer\Org;

use App\Models\Membership;
use App\Models\MembershipAbsence;
use App\Models\Position;
use App\Models\Setting;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * مَن يضيف وضع «غائب» (الدستور 23 — القسم 6) — منصوص بالحرف:
 * «**مَن يضيفه:** **مشرف عام التطوّع** أو **مشرف المسار** أو **دايركتور الكيان**
 * — لا الشخص نفسه (منعًا للتهرّب)».
 *
 * والعطب الذي تحرسه هذه الاختبارات: الإعداد كان يسمّي بوزشنًا اسمه `track_gm`
 * **وهو غير موجود في جدول `positions`** — فقائمة المسموح لهم تُترجَم إلى
 * مُعرِّفَين اثنين بدل ثلاثة، ومشرف المسار يُرَدّ **صامتًا** بلا خطأ ولا سطر سجلّ.
 * والمفتاح الخاطئ لا يصرخ: يفشل بهدوءٍ فيبدو النظام سليمًا وهو يمنع صاحب حقّ.
 */
class AbsenceAdderPositionsTest extends OrgTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // مصفوفة الصلاحيّات الحقيقيّة — فيها `delegations.*` بنطاقات الأدوار
        $this->seed(RolePermissionSeeder::class);
    }

    /** مشرف المسار: بوزشن منصوص عليه في 23-6 — ويجب أن يُقبَل */
    public function test_track_supervisor_may_open_absence(): void
    {
        $actor = $this->trackSupervisor();
        $target = $this->membershipOf('VOL-C1');

        $response = $this->actingAs($actor)->post(
            route('volunteer.department.absence', $target),
            $this->payload(),
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('membership_absences', [
            'membership_id' => $target->id,
            'created_by' => $actor->id,
        ]);
    }

    /** دايركتور الكيان: البوزشن الثالث في النصّ */
    public function test_director_may_open_absence(): void
    {
        $actor = $this->actorWithRole('VOL-DIR', 'director');
        $target = $this->membershipOf('VOL-C2');

        $this->actingAs($actor)
            ->post(route('volunteer.department.absence', $target), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('membership_absences', ['membership_id' => $target->id]);
    }

    /**
     * التيم ليدر يحمل `delegations.create@TEAM` في مصفوفة الصلاحيّات، لكنّ
     * 23-6 يحصر **إضافة الغياب** في ثلاثة بوزشنات ليس هو منها — فيُرَدّ بقائمة
     * البوزشنات لا بالمفتاح. (حارسان لا حارس: المفتاح ثمّ البوزشن.)
     */
    public function test_team_leader_is_still_refused(): void
    {
        $actor = $this->actorWithRole('VOL-TL1', 'team_leader');
        $target = $this->membershipOf('VOL-C1');

        $this->actingAs($actor)
            ->post(route('volunteer.department.absence', $target), $this->payload())
            ->assertSessionHasErrors('membership_id');

        $this->assertDatabaseMissing('membership_absences', ['membership_id' => $target->id]);
    }

    /** الكوردنيتور لا يملك المفتاح أصلًا ⟵ يقف عند الباب */
    public function test_coordinator_is_refused_at_the_gate(): void
    {
        $actor = $this->actorWithRole('VOL-C2', 'coordinator');
        $target = $this->membershipOf('VOL-C1');

        $this->actingAs($actor)
            ->post(route('volunteer.department.absence', $target), $this->payload())
            ->assertForbidden();
    }

    /** «لا الشخص نفسه — منعًا للتهرّب» */
    public function test_nobody_opens_absence_for_himself(): void
    {
        $actor = $this->trackSupervisor();
        $target = $actor->memberships()->where('status', 'active')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('volunteer.department.absence', $target), $this->payload())
            ->assertSessionHasErrors('membership_id');

        $this->assertSame(0, MembershipAbsence::where('membership_id', $target->id)->count());
    }

    /**
     * ⭐ جرد حاكم: **كلّ إعدادٍ يسمّي بوزشنًا يجب أن يسمّي بوزشنًا موجودًا**.
     *
     * لأنّ المفتاح الخاطئ هنا لا يرمي استثناءً: `whereIn('key', [...])` تُرجِع
     * صفوفًا أقلّ فحسب، فتضيع صلاحيّةُ صاحبِ حقٍّ بلا أثر. هذا الاختبار يمسح
     * جدول الإعدادات كلّه لا مفتاحًا بعينه — فأيّ إعدادٍ جديد يسمّي بوزشنًا
     * وهميًّا يسقط هنا يوم كتابته.
     */
    public function test_every_setting_that_names_positions_names_existing_ones(): void
    {
        $known = Position::query()->pluck('key')->all();

        $suspects = Setting::query()
            ->where(fn ($q) => $q->where('key', 'like', '%_position')->orWhere('key', 'like', '%_positions'))
            ->get();

        $this->assertNotEmpty($suspects, 'لا إعدادات تسمّي بوزشنات — الجرد بلا مادّة، فالحارس وهميّ.');

        foreach ($suspects as $setting) {
            foreach ($this->keysOf($setting) as $key) {
                $this->assertContains(
                    $key,
                    $known,
                    'الإعداد «'.$setting->key.'» يسمّي بوزشن «'.$key.'» وهو غير موجود في جدول positions — '
                    .'ويفشل صامتًا: القائمة تُترجَم لصفوفٍ أقلّ ولا يظهر خطأ.',
                );
            }
        }
    }

    // ------------------------------------------------------------------ أدوات

    /**
     * الرموز التي **يُقصَد بها بوزشن** داخل قيمة الإعداد.
     *
     * والتصفية بالشكل لا بالمفتاح: القيمة الرقميّة (`min_days_in_position = 30`)
     * ليست اسم بوزشن، والقيمة المركّبة (كائن JSON) مفاتيحُها هي البوزشنات
     * (`min_days_by_position`) لا قيمُها. وما بقي بعدها **رمزٌ لاتينيّ صغير**
     * وهو بالضبط شكل مفاتيح جدول `positions`.
     *
     * @return array<int,string>
     */
    private function keysOf(Setting $setting): array
    {
        $raw = $setting->value ?? $setting->default_value;

        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode((string) $raw, true);

        $values = match (true) {
            is_array($decoded) && array_is_list($decoded) => $decoded,
            is_array($decoded) => array_keys($decoded),
            default => [(string) $raw],
        };

        return array_values(array_filter(array_map(
            fn ($value) => is_string($value) && preg_match('/^[a-z][a-z0-9_]*$/', trim($value)) === 1
                ? trim($value)
                : null,
            $values,
        )));
    }

    /** مشرف عام المسار — بوزشن حقيقيّ في الجدول، وعضويّته على الكيان الرئيسيّ */
    private function trackSupervisor(): User
    {
        $root = $this->membershipOf('VOL-DIR')->entity;

        $user = User::create([
            'name' => 'إسلام عبد المنعم',
            'email' => 'track.supervisor@volunteer.local',
            'password' => 'secret-password',
            'code' => 'VOL-TRK',
            'status' => 'active',
        ]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'entity_id' => $root->id,
            'position_id' => Position::query()->where('key', 'track_supervisor')->value('id'),
            'is_primary' => true,
            'started_at' => now()->subYear(),
            'status' => 'active',
        ]);

        $user->assignRole('track_supervisor', $membership);
        app(AccessEngine::class)->forget($user);
        Cache::forget('settings');

        return $user;
    }

    /** @return array<string,string> */
    private function payload(): array
    {
        return [
            'from_date' => today()->addDay()->toDateString(),
            'to_date' => today()->addDays(4)->toDateString(),
            'reason' => 'سفر للامتحانات',
        ];
    }
}
