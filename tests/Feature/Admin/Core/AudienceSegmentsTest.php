<?php

namespace Tests\Feature\Admin\Core;

use App\Mail\AnnouncementMail;
use App\Models\AdAudience;
use App\Models\Announcement;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Track;
use App\Models\User;
use App\Services\Admin\AudienceSegments;
use App\Services\Notifications\AnnouncementFeed;
use App\Services\Notifications\AnnouncementMailer;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * شرائح الجمهور المحفوظة (12.13) وربطها بالمنشورات (12.6-أ).
 *
 * أهمّ ما هنا: **الفرق بين الديناميكيّة والثابتة فرقٌ في السلوك لا تسمية** —
 * ولذلك نغيّر بيانات مستخدمٍ بعد الحفظ ونقيس أثر التغيير على كلٍّ منهما.
 */
class AudienceSegmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    // ------------------------------------------------------------------ أدوات

    private function makeUser(string $name = 'مستخدم', string $status = 'active', array $extra = []): User
    {
        return User::create(array_merge([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => $status,
        ], $extra));
    }

    private function owner(): User
    {
        $user = $this->makeUser('مالك المنصّة');
        $user->assignRole('platform_owner');
        app(AccessEngine::class)->forget();

        return $user;
    }

    private function segments(): AudienceSegments
    {
        return app(AudienceSegments::class);
    }

    /** @param  array<int, array<string, mixed>>  $conditions */
    private function rule(array $conditions, string $match = 'all'): array
    {
        return ['match' => 'all', 'groups' => [['match' => $match, 'conditions' => $conditions]]];
    }

    // ------------------------------------------- ⭐ النوع: ديناميكيّة مقابل ثابتة

    /**
     * ⭐ الفرق حقيقيّ: بعد حفظ الشريحتين بنفس الشرط، **تغيير بيانات مستخدم**
     * يُدخِله في الديناميكيّة ولا يمسّ الثابتة (12.13).
     */
    public function test_static_segment_freezes_its_members_while_dynamic_recomputes(): void
    {
        $owner = $this->owner();
        $this->makeUser('معتمَد أوّل', 'active');
        $newcomer = $this->makeUser('تحت المراجعة', 'pending');

        $rule = $this->rule([['field' => 'status', 'values' => ['active']]]);

        $dynamic = $this->segments()->save('نشطون — ديناميكيّة', $rule, AudienceSegments::TYPE_DYNAMIC, $owner);
        $static = $this->segments()->save('نشطون — ثابتة', $rule, AudienceSegments::TYPE_STATIC, $owner);

        $before = $this->segments()->memberCount($dynamic);
        $this->assertSame($before, $this->segments()->memberCount($static), 'الشريحتان تبدآن من نفس النقطة');

        // بعد الحفظ: مستخدم جديد يستوفي الشرط
        $newcomer->update(['status' => 'active']);

        $this->assertSame($before + 1, $this->segments()->memberCount($dynamic), 'الديناميكيّة تُعاد حسبتها');
        $this->assertSame($before, $this->segments()->memberCount($static), 'الثابتة مجمَّدة على أعضائها');
        $this->assertFalse($this->segments()->contains($static, $newcomer));
        $this->assertTrue($this->segments()->contains($dynamic, $newcomer));
    }

    /** والثابتة تحتفظ بعضوٍ خرج عن الشرط — لأنّها لقطةٌ لا استعلام. */
    public function test_static_segment_keeps_a_member_who_left_the_rule(): void
    {
        $owner = $this->owner();
        $member = $this->makeUser('عضو', 'active');

        $rule = $this->rule([['field' => 'status', 'values' => ['active']]]);
        $static = $this->segments()->save('لقطة', $rule, AudienceSegments::TYPE_STATIC, $owner);

        $member->update(['status' => 'suspended']);

        $this->assertTrue($this->segments()->contains($static, $member));
    }

    // ------------------------------------------------- باني المعايير AND/OR ومجموعات

    /** الربط داخل المجموعة بـAND: مَن استوفى الشرطين معًا وحده (12.13). */
    public function test_and_inside_a_group_requires_every_condition(): void
    {
        $owner = $this->owner();
        $both = $this->makeUser('الاتنين', 'active', ['xp' => 500]);
        $this->makeUser('نشِط بلا XP', 'active', ['xp' => 0]);

        $rule = $this->rule([
            ['field' => 'status', 'values' => ['active']],
            ['field' => 'xp', 'min' => 100, 'max' => null],
        ]);

        $ids = $this->segments()->query($rule, $owner)->pluck('id');

        $this->assertTrue($ids->contains($both->id));
        $this->assertSame(1, $ids->count());
    }

    /** والربط بـOR: مَن استوفى أيّ شرط (12.13). */
    public function test_or_inside_a_group_accepts_any_condition(): void
    {
        $owner = $this->owner();
        $active = $this->makeUser('نشِط', 'active', ['xp' => 0]);
        $rich = $this->makeUser('صاحب XP', 'pending', ['xp' => 900]);
        $neither = $this->makeUser('لا ده ولا ده', 'rejected', ['xp' => 0]);

        $rule = $this->rule([
            ['field' => 'status', 'values' => ['active']],
            ['field' => 'xp', 'min' => 100, 'max' => null],
        ], 'any');

        $ids = $this->segments()->query($rule, $owner)->pluck('id');

        $this->assertTrue($ids->contains($active->id));
        $this->assertTrue($ids->contains($rich->id));
        $this->assertFalse($ids->contains($neither->id));
    }

    /** ومجموعتان تُربطان بينهما بـOR — تركيبٌ لا يُبنى بشرطٍ مسطّح واحد. */
    public function test_two_groups_can_be_joined_with_or(): void
    {
        $owner = $this->owner();
        $suspended = $this->makeUser('معلّق', 'suspended', ['xp' => 0]);
        $rich = $this->makeUser('نشِط بـXP', 'active', ['xp' => 900]);
        $plain = $this->makeUser('نشِط بلا XP', 'active', ['xp' => 0]);

        $rule = [
            'match' => 'any',
            'groups' => [
                ['match' => 'all', 'conditions' => [
                    ['field' => 'status', 'values' => ['active']],
                    ['field' => 'xp', 'min' => 100, 'max' => null],
                ]],
                ['match' => 'all', 'conditions' => [
                    ['field' => 'status', 'values' => ['suspended']],
                ]],
            ],
        ];

        $ids = $this->segments()->query($rule, $owner)->pluck('id');

        $this->assertTrue($ids->contains($rich->id));
        $this->assertTrue($ids->contains($suspended->id));
        $this->assertFalse($ids->contains($plain->id), 'نشِط بلا XP خارج المجموعتين');
    }

    /** الشرط القديم المسطّح يُقرأ كما هو — ترحيلٌ بلا فقد (2.11-د). */
    public function test_legacy_flat_rule_still_resolves(): void
    {
        $owner = $this->owner();
        $pending = $this->makeUser('تحت المراجعة', 'pending');
        $this->makeUser('نشِط', 'active');

        $ids = $this->segments()->query(['status' => 'pending'], $owner)->pluck('id');

        $this->assertTrue($ids->contains($pending->id));
        $this->assertSame(1, $ids->count());
    }

    // ------------------------------------------------------ حصر النطاق (12.2.1-ب)

    /**
     * ⭐ مَن يرى فريقه وحده لا يبني شريحةً بالمنصّة كلّها ثمّ يخاطبها:
     * المعاينة والعدّ والحلّ كلّها داخل نطاقه (12.2.1-ب).
     */
    public function test_scoped_admin_cannot_build_a_platform_wide_segment(): void
    {
        $track = Track::query()->first() ?? Track::create(['key' => 'departments', 'name_ar' => 'الأقسام']);
        $alpha = Entity::create(['track_id' => $track->id, 'name_ar' => 'قسم ألفا', 'status' => 'active']);
        $beta = Entity::create(['track_id' => $track->id, 'name_ar' => 'قسم بيتا', 'status' => 'active']);

        $leader = $this->makeUser('قائد ألفا');
        $this->place($leader, $alpha);

        $mate = $this->makeUser('زميل ألفا');
        $this->place($mate, $alpha);

        $stranger = $this->makeUser('غريب من بيتا');
        $this->place($stranger, $beta);

        $this->grant($leader, 'user_segments.list', 'ENTITY');

        $rule = $this->rule([['field' => 'status', 'values' => ['active']]]);
        $ids = $this->segments()->query($rule, $leader)->pluck('id');

        $this->assertTrue($ids->contains($mate->id), 'يرى كيانه');
        $this->assertFalse($ids->contains($stranger->id), 'لا يعبر كيانه');

        // والشريحة المحفوظة تُحلّ لاحقًا **بنطاق صاحبها** لا بنطاق من يستعملها
        $segment = $this->segments()->save('كيان ألفا', $rule, AudienceSegments::TYPE_DYNAMIC, $leader);

        $this->assertFalse($this->segments()->contains($segment, $stranger));
    }

    // -------------------------------------------------- الاستخدام والأرشفة والحذف

    /** «مستخدَمة في X مكان» رقمٌ محسوب من الاستخدام الفعليّ لا نصّ ثابت (12.13). */
    public function test_usage_is_counted_from_real_usage_with_links(): void
    {
        $owner = $this->owner();
        $segment = $this->segments()->save('شريحة مستهدَفة', $this->rule([['field' => 'status', 'values' => ['active']]]), AudienceSegments::TYPE_DYNAMIC, $owner);

        $this->assertCount(0, $this->segments()->usage($segment));

        $announcement = Announcement::create([
            'title' => 'منشور للشريحة',
            'status' => 'published',
            'audience' => ['type' => 'segment', 'ids' => [$segment->id]],
        ]);

        $usage = $this->segments()->usage($segment);

        $this->assertCount(1, $usage);
        $this->assertSame('منشور للشريحة', $usage->first()['label']);
        $this->assertStringContainsString((string) $announcement->id, $usage->first()['url']);
    }

    /** الأرشفة بدل الحذف — Toggle في الاتّجاهين، والصفّ يبقى (12.13). */
    public function test_archive_toggles_instead_of_deleting(): void
    {
        $owner = $this->owner();
        $segment = $this->segments()->save('شريحة', $this->rule([]), AudienceSegments::TYPE_DYNAMIC, $owner);

        $this->actingAs($owner)->post(route('admin.users.segments.archive', $segment))->assertRedirect();
        $this->assertNotNull($segment->refresh()->archived_at);
        $this->assertDatabaseHas('ad_audiences', ['id' => $segment->id]);

        $this->actingAs($owner)->post(route('admin.users.segments.archive', $segment))->assertRedirect();
        $this->assertNull($segment->refresh()->archived_at);
    }

    /** وحذف شريحة **مستخدَمة** يُمنَع على الخادم بتحذيرٍ يشرح البديل (12.13). */
    public function test_deleting_a_used_segment_is_refused_on_the_server(): void
    {
        $owner = $this->owner();
        $segment = $this->segments()->save('مستخدَمة', $this->rule([]), AudienceSegments::TYPE_DYNAMIC, $owner);

        Announcement::create([
            'title' => 'منشور مرتبط',
            'status' => 'published',
            'audience' => ['type' => 'segment', 'ids' => [$segment->id]],
        ]);

        $this->actingAs($owner)
            ->delete(route('admin.users.segments.destroy', $segment))
            ->assertRedirect()
            ->assertSessionHas('problem');

        $this->assertDatabaseHas('ad_audiences', ['id' => $segment->id]);
    }

    /** التكرار ينتج نسخةً مستقلّة لا تشارك أباها أعضاءه (12.13). */
    public function test_duplicate_creates_an_independent_copy(): void
    {
        $owner = $this->owner();
        $this->makeUser('عضو', 'active');
        $segment = $this->segments()->save('الأصل', $this->rule([['field' => 'status', 'values' => ['active']]]), AudienceSegments::TYPE_STATIC, $owner);

        $this->actingAs($owner)->post(route('admin.users.segments.duplicate', $segment))->assertRedirect();

        $copy = AdAudience::query()->where('name', '!=', 'الأصل')->where('kind', AudienceSegments::KIND)->latest('id')->firstOrFail();

        $this->assertSame($segment->size, $copy->size);
        $this->assertNotSame($segment->id, $copy->id);
        $this->assertSame(
            DB::table('audience_segment_members')->where('ad_audience_id', $segment->id)->count(),
            DB::table('audience_segment_members')->where('ad_audience_id', $copy->id)->count(),
        );
    }

    // ------------------------------------------------------- المعاينة اللحظيّة والشاشة

    /** المعاينة اللحظيّة تعطي العدد + عيّنة أعضاء بلا حفظ (12.13). */
    public function test_live_preview_returns_count_and_sample_without_saving(): void
    {
        $owner = $this->owner();
        $this->makeUser('نشِط للعرض', 'active');

        $this->actingAs($owner)
            ->post(route('admin.users.segments.preview'), [
                'name' => 'بلا حفظ',
                'match' => 'all',
                'groups' => [['match' => 'all', 'conditions' => [['field' => 'status', 'values' => ['active']]]]],
            ])
            ->assertOk()
            ->assertSee('المطابقون الآن')
            ->assertSee('نشِط للعرض');

        $this->assertDatabaseMissing('ad_audiences', ['name' => 'بلا حفظ']);
    }

    // ------------------------------------------------- ربط الشريحة بالمنشور (12.6-أ)

    /**
     * ⭐ المنشور يستهدف **شريحة محفوظة**، وتُحلّ إلى أعضائها **على الخادم لحظة
     * الإرسال** — لا لحظة الحفظ (12.6-أ · 12.13).
     */
    public function test_announcement_resolves_a_saved_segment_at_send_time(): void
    {
        Mail::fake();

        $owner = $this->owner();
        $inside = $this->makeUser('داخل الشريحة', 'active', ['email_verified_at' => now()]);
        $outside = $this->makeUser('برّه الشريحة', 'pending', ['email_verified_at' => now()]);

        $segment = $this->segments()->save(
            'النشطون',
            $this->rule([['field' => 'status', 'values' => ['active']]]),
            AudienceSegments::TYPE_DYNAMIC,
            $owner,
        );

        $announcement = Announcement::create([
            'title' => 'رسالة للشريحة',
            'body' => 'نصّ',
            'status' => 'published',
            'show_in_feed' => true,
            'email_enabled' => true,
            'scheduled_at' => now()->subMinute(),
            'audience' => ['type' => 'segment', 'ids' => [$segment->id]],
        ]);

        $recipients = app(AnnouncementMailer::class)->recipients($announcement)->pluck('id');

        $this->assertTrue($recipients->contains($inside->id));
        $this->assertFalse($recipients->contains($outside->id));

        app(AnnouncementMailer::class)->deliver($announcement);

        Mail::assertSent(AnnouncementMail::class, fn (AnnouncementMail $mail) => $mail->hasTo($inside->email));
        Mail::assertNotSent(AnnouncementMail::class, fn (AnnouncementMail $mail) => $mail->hasTo($outside->email));
    }

    /** والفيد يحترم نفس الشريحة: مَن ليس عضوًا لا يرى المنشور (13.2 · 12.13). */
    public function test_feed_respects_segment_targeting(): void
    {
        $owner = $this->owner();
        $inside = $this->makeUser('عضو', 'active');
        $outside = $this->makeUser('غير عضو', 'pending');

        $segment = $this->segments()->save(
            'النشطون',
            $this->rule([['field' => 'status', 'values' => ['active']]]),
            AudienceSegments::TYPE_DYNAMIC,
            $owner,
        );

        $announcement = Announcement::create([
            'title' => 'منشور الشريحة',
            'status' => 'published',
            'show_in_feed' => true,
            'scheduled_at' => now()->subMinute(),
            'audience' => ['type' => 'segment', 'ids' => [$segment->id]],
        ]);

        $feed = app(AnnouncementFeed::class);

        $this->assertTrue($feed->isVisibleTo($announcement, $inside));
        $this->assertFalse($feed->isVisibleTo($announcement, $outside));
    }

    // ------------------------------------------------------------------ داخليّ

    private function place(User $user, Entity $entity, ?Membership $upline = null): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => Position::query()->value('id'),
            'upline_id' => $upline?->id,
            'is_primary' => true,
            'started_at' => now(),
            'status' => 'active',
        ]);
    }

    private function grant(User $user, string $key, string $scope): void
    {
        [$resource, $action] = explode('.', $key);

        $permission = Permission::firstOrCreate(['key' => $key], [
            'resource' => $resource,
            'action' => $action,
            'group' => 'اختبار النطاق',
            'label_ar' => $key,
            'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
        ]);

        DB::table('permission_user')->insertOrIgnore([
            'permission_id' => $permission->id,
            'user_id' => $user->id,
            'membership_id' => null,
            'scope' => $scope,
            'effect' => 'allow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AccessEngine::class)->forget($user);
    }
}
