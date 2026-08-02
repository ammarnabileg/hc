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
}
