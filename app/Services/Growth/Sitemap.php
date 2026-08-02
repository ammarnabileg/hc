<?php

namespace App\Services\Growth;

use App\Models\Article;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\LearningPath;
use App\Services\Admin\System\ArticleWorkflow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * ⭐ `sitemap.xml` تلقائيّ (21.2-ب) — «يتحدّث مع كلّ شهادة/تدريب/مقال جديد».
 *
 * والقاعدة الحاكمة: **لا يدخل الخريطةَ إلّا المنشورُ المفهرَس**. المسودّة والمؤرشف
 * والمُطفأة فهرستُه لا يظهر — وإلّا دللنا محرّكات البحث على صفحات 404.
 */
class Sitemap
{
    public function enabled(): bool
    {
        return (bool) setting('growth.sitemap.enabled', true);
    }

    /**
     * كلّ روابط الخريطة.
     *
     * @return Collection<int,array{loc:string, lastmod:?string, changefreq:string, priority:string}>
     */
    public function urls(): Collection
    {
        return collect()
            ->merge($this->staticUrls())
            ->merge($this->courses())
            ->merge($this->paths())
            ->merge($this->articles())
            ->merge($this->certificates())
            ->values();
    }

    public function xml(): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($this->urls() as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.$this->e($url['loc']).'</loc>';

            if ($url['lastmod']) {
                $lines[] = '    <lastmod>'.$this->e($url['lastmod']).'</lastmod>';
            }

            $lines[] = '    <changefreq>'.$this->e($url['changefreq']).'</changefreq>';
            $lines[] = '    <priority>'.$this->e($url['priority']).'</priority>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines);
    }

    /** نصّ `robots.txt` — ويذكر الخريطة صراحةً وإلّا لم يجدها الزاحف (21.2-ب) */
    public function robots(): string
    {
        $lines = ['User-agent: *'];

        foreach ((array) setting('growth.robots.disallow', ['/admin', '/volunteer', '/settings', '/wallet']) as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap.xml');

        return implode("\n", $lines)."\n";
    }

    private function staticUrls(): Collection
    {
        return collect([
            $this->row(route('home'), null, 'daily', '1.0'),
            $this->row(route('growth.articles.index'), null, 'daily', '0.8'),
            $this->row(route('verify.certificate'), null, 'monthly', '0.5'),
        ]);
    }

    private function courses(): Collection
    {
        if (! (bool) setting('growth.seo.index_courses', true)) {
            return collect();
        }

        return Course::query()
            ->where('status', (string) setting('learning.course.published_status', 'published'))
            ->where('is_indexable', true)
            ->get(['slug', 'updated_at'])
            ->map(fn (Course $c) => $this->row(
                route('store.product', ['type' => 'course', 'slug' => $c->slug]),
                $c->updated_at,
                'weekly',
                '0.8',
            ));
    }

    private function paths(): Collection
    {
        if (! (bool) setting('growth.seo.index_courses', true)) {
            return collect();
        }

        return LearningPath::query()
            ->where('status', (string) setting('learning.course.published_status', 'published'))
            ->where('is_indexable', true)
            ->get(['slug', 'updated_at'])
            ->map(fn (LearningPath $p) => $this->row(
                route('store.product', ['type' => 'path', 'slug' => $p->slug]),
                $p->updated_at,
                'weekly',
                '0.7',
            ));
    }

    /** ⭐ المنشور وحده — المسودّة والمراجعة والمؤرشف لا تدخل الخريطة أبدًا */
    private function articles(): Collection
    {
        if (! (bool) setting('growth.seo.index_articles', true)) {
            return collect();
        }

        return Article::query()
            ->where('status', ArticleWorkflow::PUBLISHED)
            ->get(['slug', 'updated_at'])
            ->map(fn (Article $a) => $this->row(
                route('growth.articles.show', $a->slug),
                $a->updated_at,
                'monthly',
                '0.7',
            ));
    }

    /** ⭐ كلّ شهادة صادرة صفحةٌ عامّة مفهرسة — «فمع كلّ خرّيجٍ تكبر مساحتنا» (21.1-أ) */
    private function certificates(): Collection
    {
        if (! (bool) setting('growth.seo.index_certificates', true)) {
            return collect();
        }

        return Certificate::query()
            ->whereIn('status', (array) setting('growth.sitemap.certificate_statuses', ['valid']))
            ->limit((int) setting('growth.sitemap.max_certificates', 5000))
            ->get(['code', 'updated_at'])
            ->map(fn (Certificate $c) => $this->row(
                route('verify.certificate', ['code' => $c->code]),
                $c->updated_at,
                'yearly',
                '0.6',
            ));
    }

    private function row(string $loc, ?Carbon $lastmod, string $changefreq, string $priority): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod?->toAtomString(),
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
