<?php

namespace App\Services\Home;

use App\Models\Article;
use App\Models\Course;
use App\Models\Event;
use App\Models\LearningPath;
use Illuminate\Support\Collection;

/**
 * محتوى الصفحة الرئيسيّة العامّة (21.1-أ · 21.2).
 *
 * المبدأ: الصفحة **مفهرسة** ومحتواها **حقيقيّ** — أحدث ما نُشِر فعلًا لا قوائم
 * مزيّنة ولا أرقام وهميّة (2.9). وكلّ حدّ وعدد من `setting()` (2.13).
 */
class HomeContent
{
    /**
     * أحدث التدريبات المنشورة — والمفهرَسة منها وحدها تظهر للزائر (21.1-هـ).
     *
     * @return Collection<int,Course>
     */
    public function courses(): Collection
    {
        return Course::query()
            ->where('status', $this->publishedStatus())
            ->where('is_indexable', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($this->limit((int) setting('home.courses.limit', 4)))
            ->get();
    }

    /**
     * @return Collection<int,LearningPath>
     */
    public function paths(): Collection
    {
        return LearningPath::query()
            ->where('status', $this->publishedStatus())
            ->where('is_indexable', true)
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->limit($this->limit((int) setting('home.paths.limit', 3)))
            ->get();
    }

    /**
     * @return Collection<int,Article>
     */
    public function articles(): Collection
    {
        return Article::query()
            ->with('author:id,name,code')
            ->where('status', (string) setting('home.articles.published_status', 'published'))
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit($this->limit((int) setting('home.articles.limit', 3)))
            ->get();
    }

    /**
     * الفعاليّات القادمة وحدها — والماضية لا مكان لها في دعوة الزائر.
     *
     * @return Collection<int,Event>
     */
    public function events(): Collection
    {
        return Event::query()
            ->where('status', (string) setting('events.published_status', 'published'))
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit($this->limit((int) setting('home.events.limit', 3)))
            ->get();
    }

    /**
     * بلوكات القيمة: عنوان + سطر لكلّ بلوك — كلّها نصّ من الإعدادات (2.13).
     *
     * @return array<int,array{title:string,body:string}>
     */
    public function valueBlocks(): array
    {
        $raw = setting('home.value.items', []);
        $blocks = [];

        foreach (is_array($raw) ? $raw : [] as $item) {
            if (! is_array($item) || ! isset($item['title'])) {
                continue;
            }

            $blocks[] = [
                'title' => (string) $item['title'],
                'body' => (string) ($item['body'] ?? ''),
            ];
        }

        return $blocks;
    }

    /**
     * بيانات Schema.org من نوع Organization (21.2-ب) — نتيجة غنيّة في محرّكات
     * البحث بلا مكتبة خارجيّة: JSON-LD مكتوب بأيدينا.
     *
     * @return array<string,mixed>
     */
    public function organizationSchema(): array
    {
        $sameAs = setting('home.org.same_as', []);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => (string) setting('home.org.name', config('app.name')),
            'url' => url('/'),
            'description' => (string) setting('home.meta_description', ''),
        ];

        if ($logo = (string) setting('home.org.logo', '')) {
            $schema['logo'] = str_starts_with($logo, 'http') ? $logo : url($logo);
        }

        if (is_array($sameAs) && $sameAs !== []) {
            $schema['sameAs'] = array_values(array_filter(array_map('strval', $sameAs)));
        }

        return $schema;
    }

    /** حالة النشر المعتمَدة في مجال التعلّم — مصدر واحد فلا تختلف الرئيسيّة عن الكتالوج */
    private function publishedStatus(): string
    {
        return (string) setting('learning.course.published_status', 'published');
    }

    /** حدٌّ آمن 1–12 على قيمةٍ مقروءة سلفًا — القراءة نفسها تقع عند نقطة الاستدعاء */
    private function limit(int $value): int
    {
        return max(1, min(12, $value));
    }
}
