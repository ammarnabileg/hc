<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Announcement;
use App\Models\AppNotification;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\HelpArticle;

/**
 * التوجيه والدعم (12.6 · 24.3): التعليمات · الإشعارات · دليل المستخدم · الشكاوى.
 */
class AdminContentGuidanceTest extends AdminContentTestCase
{
    /** شكوى جاهزة للاختبار — والسيدر لا يبني واحدة إلّا لو في مستخدمون. */
    private function complaint(): Complaint
    {
        return Complaint::query()->first() ?? Complaint::create([
            'number' => 'CMP-TEST-1',
            'user_id' => $this->makeUser(['name' => 'صاحب الشكوى'])->id,
            'type' => 'complaint',
            'category' => 'المنصّة',
            'title' => 'الصفحة بطيئة عندي',
            'body' => 'بتاخد وقت طويل تفتح من الموبايل.',
            'status' => 'open',
            'wants_contact' => true,
        ]);
    }

    /** الصفحات الأربع تفتح لمن يملك الصلاحيّة وتُمنَع عمّن لا يملكها (12.2.1). */
    public function test_guidance_screens_require_permission(): void
    {
        $admin = $this->admin();
        $routes = ['admin.guidance.index', 'admin.guidance.notifications', 'admin.guidance.help', 'admin.guidance.complaints'];

        foreach ($routes as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }

        $stranger = $this->makeUser();

        foreach ($routes as $route) {
            $this->actingAs($stranger)->get(route($route))->assertForbidden();
        }
    }

    /** ⭐ إقرار «قرأتُ وفهمت» بـXP — والسقف مرّة واحدة لكلّ منشور (12.6-أ). */
    public function test_announcement_stores_acknowledge_xp_and_targeting(): void
    {
        $this->actingAs($this->admin())->post(route('admin.guidance.announcements.store'), [
            'title' => 'تعليمات الامتحان النهائيّ',
            'body' => 'اقرأ التعليمات قبل دخول الامتحان.',
            'audience_type' => 'role',
            'audience_keys' => ['trainee'],
            'requires_acknowledge' => 1,
            'acknowledge_xp' => 25,
            'reactions_enabled' => 0,
            'status' => 'published',
        ])->assertRedirect(route('admin.guidance.index'));

        $announcement = Announcement::query()->where('title', 'تعليمات الامتحان النهائيّ')->firstOrFail();

        $this->assertTrue((bool) $announcement->requires_acknowledge);
        $this->assertSame(25, (int) $announcement->acknowledge_xp);
        $this->assertFalse((bool) $announcement->reactions_enabled, 'التفاعل ممنوع لكلّ منشور على حدة');
        $this->assertSame('role', $announcement->audience['type']);
        $this->assertSame(['trainee'], $announcement->audience['keys']);
        $this->assertNotNull($announcement->expires_at, 'الأرشفة التلقائيّة لازم تتضبط من الإعدادات');
    }

    /**
     * ⭐ 12.6-أ: محرّر المنشور فيه حقل وسائط (`media_path`) وحقل نوع
     * (`type`) — العمودان موجودان على `announcements` من زمان، وكانا
     * بلا حقلٍ في الفورم يملأهما.
     */
    public function test_announcement_form_has_media_and_type_fields_and_saves_them(): void
    {
        $admin = $this->admin();

        // الحقلان يظهران في نموذج «منشور جديد»
        $this->actingAs($admin)->get(route('admin.guidance.index'))
            ->assertOk()
            ->assertSee('name="type"', false)
            ->assertSee('name="media_path"', false)
            ->assertSee('data-media-pick="media_path"', false);

        $this->actingAs($admin)->post(route('admin.guidance.announcements.store'), [
            'title' => 'منشور بوسائط ونوع',
            'audience_type' => 'all',
            'status' => 'published',
            'type' => 'critical',
            'media_path' => 'media/announcement-banner.png',
        ])->assertRedirect(route('admin.guidance.index'));

        $announcement = Announcement::query()->where('title', 'منشور بوسائط ونوع')->firstOrFail();

        $this->assertSame('critical', $announcement->type);
        $this->assertSame('media/announcement-banner.png', $announcement->media_path);

        // وعمود «النوع» يظهر في الجدول
        $this->actingAs($admin)->get(route('admin.guidance.index'))
            ->assertOk()
            ->assertSee('يحتاج إقرار');
    }

    /** ⭐ التعليمات جدولٌ لا كروتٌ — بأعمدته الدستوريّة (24 · 12.6-أ). */
    public function test_announcements_list_renders_as_a_table_with_required_columns(): void
    {
        $this->actingAs($this->admin())->post(route('admin.guidance.announcements.store'), [
            'title' => 'منشور للجدول',
            'audience_type' => 'all',
            'status' => 'published',
            'is_pinned' => 1,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.guidance.index'))->assertOk();

        $response->assertSee('<table', false);
        $response->assertSee('منشور للجدول');
        $response->assertSee('النوع');
        $response->assertSee('الجمهور');
        $response->assertSee('نسبة القراءة');
        $response->assertSee('الإقرارات');
        $response->assertSee('مثبَّت؟');
    }

    /** أقصى منشورات مثبَّتة يُفرَض على الخادم (24.3). */
    public function test_pinned_limit_is_enforced(): void
    {
        $admin = $this->admin();
        $max = (int) setting('announcements.pinned.max', 3);

        for ($i = 0; $i < $max + 2; $i++) {
            $this->actingAs($admin)->post(route('admin.guidance.announcements.store'), [
                'title' => 'منشور مثبَّت '.$i,
                'audience_type' => 'all',
                'is_pinned' => 1,
                'status' => 'published',
            ]);
        }

        $this->assertLessThanOrEqual($max, Announcement::query()->where('is_pinned', true)->count());
    }

    /** إرسال إشعار يدويّ — **برابط أو بدون** (12.6-ب). */
    public function test_manual_notification_is_sent_with_or_without_url(): void
    {
        $admin = $this->admin();
        $this->makeUser();

        $this->actingAs($admin)->post(route('admin.guidance.notifications.send'), [
            'title' => 'تذكير بموعد اللقاء',
            'body' => 'اللقاء النهارده الساعة 8 مساءً.',
            'audience_type' => 'all',
        ])->assertRedirect();

        $notification = AppNotification::query()->where('title', 'تذكير بموعد اللقاء')->firstOrFail();
        $this->assertNull($notification->url, 'الإشعار بلا رابط لازم يتبعت عادي');

        $this->actingAs($admin)->post(route('admin.guidance.notifications.send'), [
            'title' => 'افتح تدريباتك',
            'url' => '/dashboard',
            'audience_type' => 'all',
        ])->assertRedirect();

        $this->assertSame('/dashboard', AppNotification::query()->where('title', 'افتح تدريباتك')->value('url'));
    }

    /** دليل المستخدم: إنشاء وبحث و«هل كان مفيدًا؟» (12.6-ج). */
    public function test_help_article_is_created_and_rated(): void
    {
        $this->actingAs($this->admin())->post(route('admin.guidance.help.store'), [
            'title' => 'إزاي أغيّر كلمة المرور؟',
            'category' => 'الحساب',
            'tags' => 'أمان, حساب',
            'body' => 'من الإعدادات ← الخصوصيّة والأمان.',
            'status' => 'published',
        ])->assertRedirect();

        $article = HelpArticle::query()->where('title', 'إزاي أغيّر كلمة المرور؟')->firstOrFail();

        $this->assertSame(['أمان', 'حساب'], $article->tags);
        $this->assertNotEmpty($article->slug);

        $this->actingAs($this->admin())
            ->get(route('admin.guidance.help', ['q' => 'كلمة المرور']))
            ->assertOk()
            ->assertSee('إزاي أغيّر كلمة المرور؟');
    }

    /** ⭐ دليل المستخدم جدولٌ لا كروتٌ — بأعمدته الدستوريّة (24 · 12.6-ج). */
    public function test_help_articles_list_renders_as_a_table_with_required_columns(): void
    {
        $this->actingAs($this->admin())->post(route('admin.guidance.help.store'), [
            'title' => 'دليل الجدول',
            'title_en' => 'Table Guide',
            'category' => 'الحساب',
            'body' => 'محتوى.',
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.guidance.help'))->assertOk();

        $response->assertSee('<table', false);
        $response->assertSee('دليل الجدول');
        $response->assertSee('Table Guide');
        $response->assertSee('العنوان (ع/إ)');
        $response->assertSee('التصنيف');
        $response->assertSee('الحساب');
    }

    /** الشكاوى: ردّ داخليّ/خارجيّ + إغلاق بسبب موثّق (24.3). */
    public function test_complaint_reply_and_close_with_reason(): void
    {
        $admin = $this->admin();
        $complaint = $this->complaint();

        // ردّ داخليّ: لا يصل للمستخدم
        $this->actingAs($admin)->post(route('admin.guidance.complaints.reply', $complaint), [
            'body' => 'ملاحظة للفريق: نتحقّق من الرابط.',
            'is_internal' => 1,
            'status' => 'in_review',
        ])->assertRedirect();

        $this->assertTrue(
            ComplaintMessage::query()->where('complaint_id', $complaint->id)->where('is_internal', true)->exists(),
        );
        $this->assertSame('in_review', $complaint->refresh()->status);

        // ردّ خارجيّ: يوصل إشعارًا
        $this->actingAs($admin)->post(route('admin.guidance.complaints.reply', $complaint), [
            'body' => 'ظبطنا الرابط — جرّب تاني من فضلك.',
            'status' => 'in_review',
        ])->assertRedirect();

        $this->assertTrue(
            AppNotification::query()->where('user_id', $complaint->user_id)->where('category', 'complaint')->exists(),
        );

        // الإغلاق بسبب إلزاميّ
        $this->actingAs($admin)
            ->post(route('admin.guidance.complaints.close', $complaint), [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('admin.guidance.complaints.close', $complaint), [
            'reason' => 'اتحلّت',
        ])->assertRedirect();

        $complaint->refresh();

        $this->assertSame('closed', $complaint->status);
        $this->assertSame('اتحلّت', $complaint->close_reason);
        $this->assertNotNull($complaint->closed_at);
    }
}
