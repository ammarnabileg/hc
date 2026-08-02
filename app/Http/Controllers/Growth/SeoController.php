<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Services\Growth\Sitemap;
use Illuminate\Http\Response;

/**
 * ⭐ `sitemap.xml` تلقائيّ و`robots.txt` (21.2-ب) — «الأساس الذي لا يعمل ما قبله بدونه».
 *
 * ولماذا ديناميكيّ لا ملفّ ثابت؟ لأنّ الخريطة تتحدّث «مع كلّ شهادة/تدريب/مقال جديد»،
 * وملفٌّ يُكتَب مرّةً يشيخ في يومه الأوّل.
 */
class SeoController extends Controller
{
    public function __construct(private readonly Sitemap $sitemap) {}

    public function sitemap(): Response
    {
        abort_unless($this->sitemap->enabled(), 404);

        return response($this->sitemap->xml(), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age='.(int) setting('growth.sitemap.cache_seconds', 3600),
        ]);
    }

    public function robots(): Response
    {
        return response($this->sitemap->robots(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age='.(int) setting('growth.sitemap.cache_seconds', 3600),
        ]);
    }
}
