<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * التحسين التدريجيّ (2.1): رسالة `<noscript>` عربيّة تشرح **ماذا ينقص**
 * و**كيف يُفعَّل الجافاسكربت خطوة بخطوة** — في ليَاوت الحساب وليَاوت الزوّار.
 *
 * الفجوة المُصلَحة: لا `noscript` في أيّ ليَاوت إطلاقًا.
 */
class ProgressiveEnhancementTest extends SecurityTestCase
{
    /** ليَاوت الزوّار: شاشة الدخول */
    public function test_guest_layout_carries_the_noscript_screen(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<noscript>', false)
            ->assertSee(setting('ux.noscript.title'), false);
    }

    /** ليَاوت الحساب: أيّ صفحة داخليّة */
    public function test_app_layout_carries_the_noscript_screen(): void
    {
        $this->actingAs($this->makeUser('متدرّب'))
            ->get('/help')
            ->assertOk()
            ->assertSee('<noscript>', false)
            ->assertSee(setting('ux.noscript.title'), false);
    }

    /** الخطوات مذكورة واحدة واحدة — لا «فعّل الجافاسكربت» وخلاص */
    public function test_the_steps_are_spelled_out_one_by_one(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $steps = (array) setting('ux.noscript.steps');

        $this->assertGreaterThanOrEqual(3, count($steps));

        foreach ($steps as $step) {
            $response->assertSee($step, false);
        }
    }

    /** كلّ النصوص من الإعدادات — تتغيّر من اللوحة بلا تعديل كود (2.13) */
    public function test_all_texts_come_from_settings(): void
    {
        Setting::query()->where('key', 'ux.noscript.title')
            ->update(['value' => 'الجافاسكربت مقفول — كلّمنا لو محتاج مساعدة']);

        Cache::forget('settings');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('الجافاسكربت مقفول — كلّمنا لو محتاج مساعدة', false);
    }
}
