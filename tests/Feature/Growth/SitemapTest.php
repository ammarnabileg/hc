<?php

namespace Tests\Feature\Growth;

use App\Models\Article;
use App\Models\User;
use App\Services\Admin\System\ArticleWorkflow;
use Illuminate\Support\Str;

/** `sitemap.xml` تلقائيّ (21.2-ب) — ولا يدخله إلّا المنشور المفهرَس */
class SitemapTest extends GrowthTestCase
{
    public function test_sitemap_contains_published_content_only(): void
    {
        $author = User::create([
            'name' => 'كاتب',
            'email' => Str::random(8).'@test.local',
            'password' => 'secret-password',
            'code' => 'U'.Str::upper(Str::random(7)),
            'status' => 'active',
        ]);

        Article::create([
            'slug' => 'maqal-mansor-map', 'author_id' => $author->id, 'title' => 'منشور',
            'status' => ArticleWorkflow::PUBLISHED, 'published_at' => now(),
        ]);

        Article::create([
            'slug' => 'maqal-mosawada-map', 'author_id' => $author->id, 'title' => 'مسودّة',
            'status' => ArticleWorkflow::DRAFT,
        ]);

        $course = $this->makeCourse();
        $hidden = $this->makeCourse();
        $hidden->update(['status' => 'draft']);

        $body = $this->get(route('sitemap.xml'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->getContent();

        $this->assertStringContainsString('maqal-mansor-map', $body);
        $this->assertStringNotContainsString('maqal-mosawada-map', $body);
        $this->assertStringContainsString($course->slug, $body);
        $this->assertStringNotContainsString($hidden->slug, $body);
    }

    /** الفهرسة إعداد: إطفاؤها يُخرِج النوع كلّه من الخريطة (21.1-هـ) */
    public function test_disabled_indexing_removes_courses_from_sitemap(): void
    {
        $course = $this->makeCourse();
        $this->setSetting('growth.seo.index_courses', '0', 'bool');

        $this->assertStringNotContainsString(
            $course->slug,
            $this->get(route('sitemap.xml'))->getContent(),
        );
    }

    public function test_robots_points_at_the_sitemap(): void
    {
        $this->get(route('robots.txt'))
            ->assertOk()
            ->assertSee('Sitemap: '.route('sitemap.xml'));
    }

    /**
     * ⭐ الحارس ضدّ عودة الملفّ الثابت: `php artisan serve` (وأيّ خادم PHP مدمج
     * أو Nginx بقاعدة `try_files`) **يردّ الملفّ الثابت في `public/` قبل أن
     * يصل الطلب إلى الراوتر إطلاقًا** — فلو عاد `public/robots.txt`، صار
     * `SeoController::robots()` كودًا ميّتًا مرّةً أخرى **ولن يمسك ذلك أيّ
     * اختبار PHPUnit** لأنّ عميل الاختبار يُرسِل الطلب مباشرةً إلى نواة
     * Laravel متجاوزًا طبقة الخادم كلّها (أثبتنا هذا بـ`curl` حقيقيّ وقت
     * التنفيذ: الملفّ الثابت يعود بنصّ `Sitemap: /sitemap.xml` نسبيًّا رغم
     * أنّ هذا الاختبار نفسه يبقى ناجحًا). فهذا الفحص وحده يمسك الطفرة.
     */
    public function test_no_static_robots_file_shadows_the_controller(): void
    {
        $this->assertFileDoesNotExist(
            public_path('robots.txt'),
            'ملفّ robots.txt ثابت في public/ يسبق الراوتر ويُسكِت المتحكّم — Sitemap::robots() يصير كودًا ميّتًا.',
        );
    }
}
