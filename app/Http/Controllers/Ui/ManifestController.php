<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Services\Ui\DesignTokens;
use Illuminate\Http\JsonResponse;

/**
 * ملفّ تعريف تطبيق الويب (`/site.webmanifest`): «أضِف إلى الشاشة الرئيسيّة» على الموبايل
 * يعرض اسم المنصّة وأيقونتها (النقطة الحمراء على الكريميّ) ويفتحها بلا إطار متصفّح،
 * بألوان النظام نفسها (DesignTokens) واسمٍ من الإعدادات لا نصٍّ محروق (2.13).
 */
class ManifestController extends Controller
{
    public function __invoke(DesignTokens $tokens): JsonResponse
    {
        $light = $tokens->variables();

        return response()->json([
            'name' => config('app.name'),
            'short_name' => (string) setting('ux.pwa.short_name', config('app.name')),
            'description' => (string) setting('ux.footer.tagline', 'تعلّم يصنع أثرًا'),
            'lang' => 'ar',
            'dir' => 'rtl',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => $light['--surface'] ?? '#fcfbf8',
            'theme_color' => $light['--surface'] ?? '#fcfbf8',
            'icons' => [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], 200, ['Cache-Control' => 'public, max-age='.max(60, (int) setting('ux.pwa.cache_seconds', 86400))], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
