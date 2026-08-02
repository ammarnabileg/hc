<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\AppNotification;
use App\Models\Course;
use App\Models\Currency;
use App\Models\Exam;
use App\Models\LearningPath;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\Learning\CredentialService;
use App\Services\Notifications\AnnouncementAcknowledger;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Facades\Cache;

/**
 * أعطال أثبتها التدقيق بالتشغيل — كلٌّ منها له اختبارٌ يمنع عودته:
 *   1) «حفظ واستمرار» لا يُنزِل منشورًا إلى مسودّة (12.4-ب).
 *   2) الحفظ التلقائيّ **كدرافت** لا على السجلّ الحيّ (12.4-ب).
 *   3) سعر امتحان المسار **واحد في الشاشتين** (12.4-أ).
 *   4) حدّ الهدوء **يمنع فعلًا**، والمتشابه **يتجمّع فعلًا** (12.6-ب).
 *   5) تذاكر الإقرار **تُصرَف** (12.6-أ).
 */
class AdminContentAuditFixesTest extends AdminContentTestCase
{
    // ======================================================== 12.4-ب: الحفظ

    /**
     * ⭐ العطل: `continue=1` كان يفرض `draft` فوق `status=published`، فيسحب
     * تدريبًا حيًّا من تحت أقدام المتدرّبين بضغطة زرٍّ يظنّها المحرّر حفظًا مؤقّتًا.
     */
    public function test_save_and_continue_does_not_demote_a_published_course(): void
    {
        $course = Course::query()->firstOrFail();
        $course->update(['status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->admin())
            ->put(route('admin.courses.update', $course), [
                'name_ar' => $course->name_ar,
                'status' => 'published',
                'continue' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('published', $course->refresh()->status);
    }

    /** والجديد بـ«حفظ واستمرار» يبدأ مسودّةً — هذا هو نصّ 12.4-ب. */
    public function test_save_and_continue_creates_a_new_course_as_draft(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.courses.store'), [
                'name_ar' => 'تدريب قيد التحرير',
                'status' => 'published',
                'continue' => 1,
            ])
            ->assertRedirect();

        $course = Course::query()->where('name_ar', 'تدريب قيد التحرير')->firstOrFail();

        $this->assertSame('draft', $course->status);
    }

    /** و«حفظ» الصريح ينشر كما اختار الأدمن — لا التفاف على قراره. */
    public function test_plain_save_applies_the_chosen_status(): void
    {
        $course = Course::query()->firstOrFail();
        $course->update(['status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->admin())
            ->put(route('admin.courses.update', $course), [
                'name_ar' => $course->name_ar,
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertSame('draft', $course->refresh()->status);
    }

    /**
     * ⭐ «حفظ تلقائيّ **كدرافت**» (12.4-ب): على تدريبٍ منشور لا يُكتَب على السجلّ
     * الحيّ — فلا يرى المتدرّبون اسمًا كان المحرّر يجرّبه فقط.
     */
    public function test_autosave_on_a_published_course_writes_a_side_draft_only(): void
    {
        $course = Course::query()->firstOrFail();
        $course->update(['status' => 'published', 'published_at' => now()]);
        $original = $course->name_ar;

        $this->actingAs($this->admin())
            ->postJson(route('admin.courses.autosave', $course), ['name_ar' => 'اسم تحت التجربة'])
            ->assertOk()
            ->assertJson(['draft' => true]);

        $course->refresh();

        $this->assertSame($original, $course->name_ar);
        $this->assertSame('اسم تحت التجربة', $course->draft_payload['name_ar'] ?? null);
    }

    /** وعلى المسودّة لا شيء يُحمى منه — الحفظ التلقائيّ يسري مباشرةً. */
    public function test_autosave_on_a_draft_course_applies_directly(): void
    {
        $course = Course::query()->firstOrFail();
        $course->update(['status' => 'draft']);

        $this->actingAs($this->admin())
            ->postJson(route('admin.courses.autosave', $course), ['name_ar' => 'اسم المسودّة'])
            ->assertOk();

        $this->assertSame('اسم المسودّة', $course->refresh()->name_ar);
    }

    /** والحفظ الصريح يُنهي مسوّدة التحرير المعلّقة. */
    public function test_explicit_save_clears_the_pending_edit_draft(): void
    {
        $course = Course::query()->firstOrFail();
        $course->update(['status' => 'published', 'published_at' => now()]);

        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.courses.autosave', $course), ['name_ar' => 'مؤقّت']);
        $this->assertNotEmpty($course->refresh()->draft_payload);

        $this->actingAs($admin)->put(route('admin.courses.update', $course), [
            'name_ar' => 'الاسم النهائيّ',
            'status' => 'published',
        ])->assertRedirect();

        $course->refresh();

        $this->assertSame('الاسم النهائيّ', $course->name_ar);
        $this->assertNull($course->draft_payload);
    }

    // ================================================ 12.4-أ: سعر امتحان المسار

    /**
     * ⭐ العطل: للسعر ثلاثة مصادر — عمود المسار وصفّ الامتحان وإعدادٌ عامّ يتيم —
     * فظهر لمسارٍ 0 في شاشة الأدمن و150 في شاشة المتدرّب. المصدر واحد الآن.
     */
    public function test_path_exam_price_is_identical_on_both_screens(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.paths.store'), [
            'name_ar' => 'مسار القيادة الميدانيّة',
            'status' => 'published',
            'exam_price_coins' => 90,
        ])->assertRedirect();

        $path = LearningPath::query()->where('name_ar', 'مسار القيادة الميدانيّة')->firstOrFail();

        // شاشة المتدرّب
        $trainee = app(CredentialService::class)->pathExam($path);

        // شاشة الأدمن
        $adminPrice = app(\App\Services\Admin\Content\PathCourseService::class)
            ->examPricesFor(collect([$path]))[$path->id];

        $this->assertTrue($trainee['exists']);
        $this->assertSame(90, $trainee['price']);
        $this->assertSame(90.0, $adminPrice);
    }

    /** ومسارٌ له سعرٌ في الأدمن يُنشَأ له صفّ امتحان تلقائيًّا — وإلّا لا امتحان أصلًا. */
    public function test_every_priced_path_gets_an_exam_row(): void
    {
        $this->actingAs($this->admin())->post(route('admin.paths.store'), [
            'name_ar' => 'مسار بلا امتحان سابق',
            'status' => 'published',
            'exam_price_coins' => 120,
        ])->assertRedirect();

        $path = LearningPath::query()->where('name_ar', 'مسار بلا امتحان سابق')->firstOrFail();

        $this->assertTrue(
            Exam::query()
                ->where('examable_type', $path->getMorphClass())
                ->where('examable_id', $path->id)
                ->where('price_coins', 120)
                ->exists(),
        );
    }

    // ================================================ 12.6-ب: حدّ الهدوء والتجميع

    /**
     * ⭐ العطل: `notifications.rate_limit.per_user_per_day` كان يُقرأ للعرض فقط،
     * فتَعِد الشاشة الأدمن بحمايةٍ غير موجودة — و6 إشعارات كانت تصل ستّتها.
     */
    public function test_quiet_limit_actually_stops_the_flood(): void
    {
        $this->setSetting('notifications.rate_limit.per_user_per_day', '3');
        $user = $this->makeUser();

        // عناوين مختلفة كي لا يخلط التجميعُ الاختبارَ
        for ($i = 1; $i <= 6; $i++) {
            Notifier::send($user, 'order', 'إشعار رقم '.$i);
        }

        $delivered = AppNotification::query()
            ->where('user_id', $user->id)
            ->where('category', 'order')
            ->count();

        $this->assertSame(3, $delivered, 'الزيادة فوق حدّ الهدوء لا تُسلَّم كصفوف مستقلّة');

        $digest = AppNotification::query()
            ->where('user_id', $user->id)
            ->where('category', 'digest')
            ->first();

        $this->assertNotNull($digest, 'الزيادة تتجمّع في إشعار واحد بدل ما تنهال عليه');
        $this->assertSame(3, (int) $digest->group_count);
    }

    /** والفئات الحرجة تمرّ دائمًا — تأجيل «شهادتك صدرت» ضررُه أكبر من نفعه. */
    public function test_exempt_categories_bypass_the_quiet_limit(): void
    {
        $this->setSetting('notifications.rate_limit.per_user_per_day', '1');
        $user = $this->makeUser();

        Notifier::send($user, 'order', 'طلب');
        Notifier::send($user, 'certificate', 'شهادة أولى');
        Notifier::send($user, 'certificate', 'شهادة ثانية');

        $this->assertSame(2, AppNotification::query()
            ->where('user_id', $user->id)
            ->where('category', 'certificate')
            ->count());
    }

    /** ⭐ التجميع يدمج فعلًا (12.6-ب) — لا عدّاد عرضٍ يحصي ما لم يُدمَج. */
    public function test_similar_notifications_merge_into_one(): void
    {
        $user = $this->makeUser();

        Notifier::send($user, 'order', 'اكتمل طلبك');
        Notifier::send($user, 'order', 'اكتمل طلبك');
        Notifier::send($user, 'order', 'اكتمل طلبك');

        $rows = AppNotification::query()->where('user_id', $user->id)->where('category', 'order')->get();

        $this->assertCount(1, $rows);
        $this->assertSame(3, (int) $rows->first()->group_count);
    }

    // ================================================ 12.6-أ: تذاكر الإقرار

    /**
     * ⭐ العطل: `acknowledge_tickets` كان يُدخَل ويُتحقَّق منه ويُحفَظ — **وبلا قارئ**،
     * فمنشورٌ بـ7 تذاكر لا يُنتِج صفّ تذاكر واحدًا.
     */
    public function test_acknowledge_tickets_are_actually_granted(): void
    {
        $user = $this->makeUser();
        $announcement = Announcement::create([
            'title' => 'تعليمات مهمّة',
            'status' => 'published',
            'requires_acknowledge' => true,
            'acknowledge_xp' => 0,
            'acknowledge_tickets' => 7,
            'audience' => ['type' => 'all'],
        ]);

        app(AnnouncementAcknowledger::class)->acknowledge($announcement, $user);

        $ticketsCurrency = Currency::query()->where('code', 'tickets')->value('id');

        $granted = (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $ticketsCurrency)
            ->where('reference_type', $announcement->getMorphClass())
            ->where('reference_id', $announcement->id)
            ->sum('amount');

        $this->assertSame(7.0, $granted);
    }

    /** ولا تُصرَف مرّتين مهما تكرّر الطلب — الإقرار مرّة واحدة خادميًّا. */
    public function test_acknowledge_tickets_are_granted_only_once(): void
    {
        $user = $this->makeUser();
        $announcement = Announcement::create([
            'title' => 'تعليمات مكرّرة',
            'status' => 'published',
            'requires_acknowledge' => true,
            'acknowledge_tickets' => 5,
            'audience' => ['type' => 'all'],
        ]);

        $acknowledger = app(AnnouncementAcknowledger::class);
        $acknowledger->acknowledge($announcement, $user);
        $acknowledger->acknowledge($announcement, $user);

        $ticketsCurrency = Currency::query()->where('code', 'tickets')->value('id');

        $this->assertSame(5.0, (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $ticketsCurrency)
            ->where('reference_id', $announcement->id)
            ->sum('amount'));

        $this->assertSame(1, AnnouncementRead::query()
            ->where('announcement_id', $announcement->id)
            ->where('user_id', $user->id)
            ->count());
    }

    /**
     * ⭐ سقفٌ واحد لمفتاحٍ واحد: كان الفورم يسمح بـ300 (سقف 500) ويُصرَف 50 بلا
     * تنبيه — لأنّ نفس المفتاح كان له افتراضيّان في ملفّين.
     */
    public function test_acknowledge_xp_cap_matches_the_form_validation(): void
    {
        $announcement = new Announcement(['acknowledge_xp' => 300]);

        $this->assertSame(300, app(AnnouncementAcknowledger::class)->rewardAmount($announcement));
    }

    private function setSetting(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], [
            'group' => 'notifications',
            'label_ar' => $key,
            'type' => 'number',
            'value' => $value,
            'default_value' => $value,
        ]);

        Cache::forget('settings');
    }
}
