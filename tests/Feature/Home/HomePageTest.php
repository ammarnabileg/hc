<?php

namespace Tests\Feature\Home;

use App\Models\Article;
use App\Models\Event;
use App\Models\Setting;

/** الصفحة الرئيسيّة العامّة (21.1 · 21.2 · 2.5-د) */
class HomePageTest extends HomeTestCase
{
    /** تعمل للزائر بلا تسجيل، وبالعربيّة، وبلا أثر لصفحة Laravel الافتراضيّة */
    public function test_landing_works_for_a_guest_in_arabic(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('اتعلّم مهارة حقيقيّة، واطلع بشهادة تقدر تثبتها.')
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertDontSee('Laravel')
            ->assertDontSee('Documentation');
    }

    /** المستخدم المسجَّل لا شأن له بصفحة الهبوط فيُحوَّل للوحته */
    public function test_authenticated_user_is_redirected_to_the_dashboard(): void
    {
        $this->actingAs($this->user())->get('/')->assertRedirect(route('dashboard'));
    }

    /** مفهرسة: ميتا من الإعدادات + Schema.org Organization + بلا noindex (21.2-ب) */
    public function test_page_is_indexable_with_meta_and_organization_schema(): void
    {
        Setting::where('key', 'home.meta_title')->update(['value' => 'عنوان الميتا المخصّص']);
        Setting::where('key', 'home.meta_description')->update(['value' => 'وصف الميتا المخصّص']);
        Setting::where('key', 'home.org.name')->update(['value' => 'جهة الاختبار']);
        cache()->forget('settings');

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>عنوان الميتا المخصّص</title>', false)
            ->assertSee('وصف الميتا المخصّص')
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('جهة الاختبار')
            ->assertDontSee('name="robots" content="noindex"', false);
    }

    /** التسجيل والتفعيل مجّانيّان — مكتوبة صراحةً (2.5-د · 21.1-د) */
    public function test_free_signup_block_is_stated_explicitly(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('التسجيل والتفعيل مجّانيّان')
            ->assertSee('تفعيل الحساب مجّانيّ');
    }

    /** المنشور وحده يظهر — والمسودّة وغير المفهرَس لا مكان لهما في صفحة عامّة */
    public function test_only_published_and_indexable_content_is_listed(): void
    {
        $this->makeCourse(['name_ar' => 'تدريب منشور ظاهر']);
        $this->makeCourse(['name_ar' => 'تدريب مسودّة مخفيّ', 'status' => 'draft']);
        $this->makeCourse(['name_ar' => 'تدريب غير مفهرَس', 'is_indexable' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSee('تدريب منشور ظاهر')
            ->assertDontSee('تدريب مسودّة مخفيّ')
            ->assertDontSee('تدريب غير مفهرَس');
    }

    /** المقالات المنشورة والفعاليّات القادمة — والماضية لا تُعرَض */
    public function test_articles_and_upcoming_events_are_listed(): void
    {
        $author = $this->user('trainee', 'كاتب المقال');

        Article::create([
            'slug' => 'art-1', 'author_id' => $author->id, 'title' => 'مقال منشور للزائر',
            'excerpt' => 'مقتطف قصير', 'status' => 'published', 'published_at' => now()->subHour(),
        ]);
        Article::create([
            'slug' => 'art-2', 'author_id' => $author->id, 'title' => 'مقال مسودّة',
            'status' => 'draft',
        ]);

        Event::create([
            'slug' => 'ev-1', 'title_ar' => 'فعاليّة قادمة للزائر', 'mode' => 'online',
            'starts_at' => now()->addDays(3), 'status' => 'published',
        ]);
        Event::create([
            'slug' => 'ev-2', 'title_ar' => 'فعاليّة قديمة', 'mode' => 'online',
            'starts_at' => now()->subDays(3), 'status' => 'published',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('مقال منشور للزائر')
            ->assertDontSee('مقال مسودّة')
            ->assertSee('فعاليّة قادمة للزائر')
            ->assertDontSee('فعاليّة قديمة');
    }

    /** كلّ نصّ من setting() — يغيّره الأدمن فيتغيّر فورًا بلا كود (2.13) */
    public function test_every_text_comes_from_settings(): void
    {
        Setting::where('key', 'home.hero.title')->update(['value' => 'عنوان بطل غيّره الأدمن']);
        Setting::where('key', 'home.cta.button')->update(['value' => 'زرّ غيّره الأدمن']);
        cache()->forget('settings');

        $this->get('/')
            ->assertOk()
            ->assertSee('عنوان بطل غيّره الأدمن')
            ->assertSee('زرّ غيّره الأدمن');
    }

    /** الحالة الفارغة سطر واحد + زرّ واحد، وتشجّع ولا تعاتب (2.15-د · 2.17-ج) */
    public function test_empty_state_encourages_and_offers_one_action(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('التدريبات الأولى في الطريق')
            ->assertDontSee('لا توجد بيانات');
    }
}
