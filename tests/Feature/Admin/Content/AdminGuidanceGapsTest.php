<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Announcement;
use App\Models\AnnouncementPollVote;
use App\Models\AnnouncementRead;
use App\Models\Permission;
use App\Models\User;
use App\Services\Admin\Content\AnnouncementRecurrence;
use App\Services\Notifications\AnnouncementFeed;
use App\Support\Access\AccessEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * بنود 12.6-أ التي كان الدستور يَعِد بها ولا تنفيذ لها:
 * **الاستطلاع** · **الجدولة المتكرّرة** · **المعاينة على الأجهزة** ·
 * **تصدير التحليلات** · **أفضل توقيت**.
 */
class AdminGuidanceGapsTest extends AdminContentTestCase
{
    /**
     * أدمن تعليمات بلا صلاحيّة بناء الاستطلاع — لاختبار «المحظور يُخفى لا
     * يُعطَّل» (2.15-أ-7) وأنّ المنع على الخادم لا في الواجهة وحدها.
     *
     * @param  array<int, string>  $keys
     */
    private function limitedAdmin(array $keys): User
    {
        $user = $this->makeUser(['name' => 'أدمن تعليمات محدود']);

        foreach ($keys as $key) {
            [$resource, $action] = array_pad(explode('.', $key, 2), 2, 'view');

            $permission = Permission::firstOrCreate(['key' => $key], [
                'resource' => $resource,
                'action' => $action,
                'group' => 'اختبار',
                'label_ar' => $key,
                'allowed_scopes' => ['ALL'],
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

        return $user;
    }

    /**
     * بناء الاستطلاع سلطةٌ مستقلّة (`announcement_polls.create`): مَن لا يملكها
     * **لا يرى حقوله**، ولو أرسلها بيده لا تُقبَل منه.
     */
    public function test_poll_fields_are_hidden_and_refused_without_their_permission(): void
    {
        $admin = $this->limitedAdmin(['announcements.list', 'announcements.view', 'announcements.create']);

        // مخفيّة لا معطَّلة
        $this->actingAs($admin)->get(route('admin.guidance.index'))
            ->assertOk()
            ->assertDontSee('سؤال الاستطلاع');

        // ومرفوضة على الخادم لو أُرسلت يدويًّا
        $this->actingAs($admin)->post(route('admin.guidance.announcements.store'), [
            'title' => 'محاولة استطلاع',
            'audience_type' => 'all',
            'status' => 'draft',
            'poll_question' => 'سؤال مهرَّب؟',
            'poll_options' => ['أ', 'ب'],
        ])->assertRedirect();

        $this->assertNull(Announcement::query()->where('title', 'محاولة استطلاع')->value('poll_question'));
    }

    /** ⭐ اختيار «عامّ النتيجة» أو «مخفيّها» يُحفَظ كما اختاره الأدمن لا كما نتمنّى. */
    public function test_poll_is_saved_with_the_chosen_visibility(): void
    {
        $this->actingAs($this->admin())->post(route('admin.guidance.announcements.store'), [
            'title' => 'استطلاع ميعاد اللقاء',
            'audience_type' => 'all',
            'status' => 'published',
            'poll_question' => 'أنسب ميعاد؟',
            'poll_options' => ['بعد المغرب', 'بعد العشاء', ''],
            'poll_results_public' => 1,
        ])->assertRedirect(route('admin.guidance.index'));

        $announcement = Announcement::query()->where('title', 'استطلاع ميعاد اللقاء')->firstOrFail();

        $this->assertSame('أنسب ميعاد؟', $announcement->poll_question);
        // الخيار الفارغ يُسقَط ولا يُخزَّن كخيارٍ بلا نصّ
        $this->assertSame(['بعد المغرب', 'بعد العشاء'], $announcement->poll_options);
        $this->assertTrue($announcement->poll_results_public);
    }

    /** سؤالٌ بخيارٍ واحد ليس استطلاعًا — فلا يُخزَّن نصفَ ميزة (12.6-أ). */
    public function test_a_poll_with_one_option_is_not_stored(): void
    {
        $this->actingAs($this->admin())->post(route('admin.guidance.announcements.store'), [
            'title' => 'استطلاع ناقص',
            'audience_type' => 'all',
            'status' => 'draft',
            'poll_question' => 'سؤال؟',
            'poll_options' => ['خيار واحد'],
        ]);

        $this->assertNull(Announcement::query()->where('title', 'استطلاع ناقص')->value('poll_question'));
    }

    /**
     * ⭐ الجدولة المتكرّرة: القالب **لا يُبَثّ بنفسه**، وكلّ دورة منشورٌ جديد
     * حتّى تتجدّد القراءة والإقرار بدل أن يُقفَلا للأبد على من قرأ أوّل مرّة.
     */
    public function test_recurring_template_is_never_broadcast_and_spawns_fresh_occurrences(): void
    {
        $this->actingAs($this->admin())->post(route('admin.guidance.announcements.store'), [
            'title' => 'تذكير أسبوعيّ',
            'body' => 'راجع مهامّك.',
            'audience_type' => 'all',
            'status' => 'published',
            'recurrence' => 'weekly',
            'scheduled_at' => now()->subDay()->toDateTimeString(),
        ]);

        $template = Announcement::query()->where('title', 'تذكير أسبوعيّ')->firstOrFail();
        $reader = $this->makeUser(['name' => 'قارئ التذكير']);
        $feed = app(AnnouncementFeed::class);

        // القالب نفسه لا يصل أحدًا
        $this->assertFalse($feed->isVisibleTo($template, $reader), 'قالب التكرار اتبَثّ بنفسه.');

        $recurrence = app(AnnouncementRecurrence::class);
        $first = $recurrence->run(CarbonImmutable::now());

        $this->assertSame(1, $first['generated']);

        $occurrence = Announcement::query()->where('recurrence_parent_id', $template->id)->firstOrFail();
        $this->assertTrue($feed->isVisibleTo($occurrence, $reader), 'الدورة المولَّدة ما وصلتش الجمهور.');
        $this->assertNull($occurrence->recurrence, 'الدورة ورثت التكرار فهتولّد دورات من نفسها.');

        // تشغيلٌ ثانٍ في نفس اللحظة لا يكرّر الدورة — وإلّا أُغرِق الناس
        $this->assertSame(0, $recurrence->run(CarbonImmutable::now())['generated']);

        // وبعد أسبوع تخرج الدورة التالية
        $this->assertSame(1, $recurrence->run(CarbonImmutable::now()->addWeek())['generated']);
        $this->assertSame(2, Announcement::query()->where('recurrence_parent_id', $template->id)->count());
    }

    /** المدى ينتهي فيتوقّف التكرار — ولا يُحذَف القالب (2.11-د: لا حذف أعمى). */
    public function test_recurrence_stops_at_its_end_date_without_deleting_the_template(): void
    {
        $template = Announcement::create([
            'title' => 'تكرار منتهٍ',
            'status' => 'published',
            'audience' => ['type' => 'all'],
            'recurrence' => 'daily',
            'recurrence_until' => now()->subDay(),
            'scheduled_at' => now()->subWeek(),
        ]);

        $result = app(AnnouncementRecurrence::class)->run(CarbonImmutable::now());

        $this->assertSame(0, $result['generated']);
        $this->assertSame(1, $result['ended']);
        $this->assertDatabaseHas('announcements', ['id' => $template->id, 'recurrence' => null]);
    }

    /** ⭐ معاينة على الأجهزة (موبايل ⇄ ديسكتوب) قبل النشر — بصلاحيّتها. */
    public function test_device_preview_renders_for_both_devices_and_is_gated(): void
    {
        $announcement = Announcement::create([
            'title' => 'مسودّة للمعاينة',
            'body' => 'مرحبًا [اسم] — النصّ بيتخصّص وقت العرض.',
            'status' => 'draft',
            'audience' => ['type' => 'all'],
        ]);

        $admin = $this->admin();

        $mobile = $this->actingAs($admin)->get(route('admin.guidance.preview', ['announcement' => $announcement, 'device' => 'mobile']))->assertOk();
        $mobile->assertSee('موبايل');
        $mobile->assertSee('مرحبًا '.$admin->shortName(), false);
        // المعاينة تعرض النصّ **مركَّبًا** لا بوسمٍ خامّ — أمّا قائمة الوسوم أسفل
        // الصفحة فشرحٌ للأدمن لا نصُّ المنشور، فالفحص على المنشور نفسه.
        $this->assertStringNotContainsString('[اسم]', (string) $mobile->viewData('announcement')->body);
        $this->assertSame('mobile', $mobile->viewData('device'));

        $desktop = $this->actingAs($admin)->get(route('admin.guidance.preview', ['announcement' => $announcement, 'device' => 'desktop']))->assertOk();
        $this->assertSame('desktop', $desktop->viewData('device'));

        // جهازٌ مخترَع يرجع للافتراضيّ بدل ما يكسر الشاشة
        $this->assertSame('mobile', $this->actingAs($admin)
            ->get(route('admin.guidance.preview', ['announcement' => $announcement, 'device' => 'tv']))
            ->viewData('device'));

        $this->actingAs($this->makeUser())
            ->get(route('admin.guidance.preview', $announcement))
            ->assertForbidden();
    }

    /** المعاينة لا تكتب في القاعدة: الأدمن «بيتفرّج» لا بيصوّت في استطلاعه. */
    public function test_preview_does_not_expose_write_actions(): void
    {
        $announcement = Announcement::create([
            'title' => 'استطلاع للمعاينة',
            'status' => 'draft',
            'audience' => ['type' => 'all'],
            'poll_question' => 'رأيك؟',
            'poll_options' => ['أ', 'ب'],
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.guidance.preview', $announcement))
            ->assertOk()
            ->assertDontSee(route('announcements.poll', $announcement), false);
    }

    /** ⭐ تصدير التحليلات CSV — وبصلاحيّة تصديرٍ مستقلّة عن صلاحيّة العرض. */
    public function test_analytics_export_carries_readers_and_is_gated(): void
    {
        $announcement = Announcement::create([
            'title' => 'منشور للتصدير',
            'status' => 'published',
            'audience' => ['type' => 'all'],
        ]);

        $reader = $this->makeUser(['name' => 'قارئة مجتهدة']);

        AnnouncementRead::create([
            'announcement_id' => $announcement->id,
            'user_id' => $reader->id,
            'read_at' => now(),
            'acknowledged_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.guidance.analytics.export', $announcement))
            ->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('قارئة مجتهدة', $csv);
        $this->assertStringContainsString($reader->code, $csv);

        $this->actingAs($this->makeUser())
            ->get(route('admin.guidance.analytics.export', $announcement))
            ->assertForbidden();
    }

    /** ⭐ «أفضل توقيت» يُحسَب من لحظات القراءة نفسها لا من لحظات النشر. */
    public function test_best_time_points_at_the_hour_people_actually_read(): void
    {
        $announcement = Announcement::create([
            'title' => 'منشور التوقيت',
            'status' => 'published',
            'audience' => ['type' => 'all'],
        ]);

        // 21:00 هي ساعة الذروة الحقيقيّة — وثلاث قراءات في 9 صباحًا تشويش
        foreach ([21, 21, 21, 21, 9, 9] as $index => $hour) {
            AnnouncementRead::create([
                'announcement_id' => $announcement->id,
                'user_id' => $this->makeUser(['name' => 'قارئ '.$index])->id,
                'read_at' => now()->subDays(2)->setTime($hour, 10),
            ]);
        }

        $response = $this->actingAs($this->admin())
            ->get(route('admin.guidance.analytics', $announcement))
            ->assertOk();

        $this->assertSame(21, $response->viewData('bestTime')['best_hour']);
        $response->assertSee('أفضل توقيت');
    }

    /** نتيجة الاستطلاع تُعرَض للأدمن في التحليلات — سلطةٌ بصلاحيّتها لا تسريب. */
    public function test_admin_analytics_shows_poll_tally_even_when_hidden_from_readers(): void
    {
        $announcement = Announcement::create([
            'title' => 'استطلاع مخفيّ النتيجة',
            'status' => 'published',
            'audience' => ['type' => 'all'],
            'poll_question' => 'رأيك؟',
            'poll_options' => ['أ', 'ب'],
            'poll_results_public' => false,
        ]);

        AnnouncementPollVote::create([
            'announcement_id' => $announcement->id,
            'user_id' => $this->makeUser(['name' => 'مصوّت'])->id,
            'option_index' => 1,
        ]);

        $poll = $this->actingAs($this->admin())
            ->get(route('admin.guidance.analytics', $announcement))
            ->assertOk()
            ->viewData('poll');

        $this->assertSame([0, 1], $poll['tally']['counts']);
        $this->assertFalse($poll['public']);
    }
}
