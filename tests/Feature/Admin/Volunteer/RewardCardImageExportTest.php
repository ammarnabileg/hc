<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * ⭐ زرّ «**حفظ الصورة**» بعد المنح (12.9) — وعدٌ كان لا يقع.
 *
 * ================== النصّ الحاكم حرفيًّا ==================
 * «**بعد التنفيذ:** بطاقة احترافيّة لكلّ مستلِم (صورته + القيمة مكتوبة عليها)
 * + «**حفظ الصورة**» + **أسهم يمين/شمال** للتنقّل بين المستلمين.»
 *
 * والمنفَّذ كان: `document.getElementById('card-save').addEventListener('click',
 * () => window.print())` — أي **طباعة متصفّح**، لا صورة. فصار الزرّ رابطًا
 * موقَّعًا إلى الرسّام على الخادم (12.14-هـ · 12.14-و) يُنزّل **PNG حقيقيًّا**.
 */
class RewardCardImageExportTest extends AdminVolunteerTestCase
{
    /** ⭐ الزرّ صار رابط تنزيلٍ موقَّعًا — لا `window.print()` */
    public function test_save_image_button_is_a_signed_download_link_not_browser_print(): void
    {
        $admin = $this->grant(
            $this->makeUser(),
            'manual_rewards.list', 'manual_rewards.create', 'image_export.use',
        );
        $target = $this->makeUser('متدرّب');

        $response = $this->actingAs($admin)->post(route('admin.rewards.grant'), [
            'codes' => $target->code,
            'currency' => 'coins',
            'direction' => 'credit',
            'amount' => 120,
            'reason' => 'bonus',
            'confirm' => 1,
        ])->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('احفظ الصورة', $html);
        $this->assertStringContainsString('data-card-save="0"', $html);
        $this->assertStringContainsString('/export/image', $html);
        // ⛔ الوعد لا يُستبدَل بالطباعة
        $this->assertStringNotContainsString('window.print()', $html);
    }

    /** ⭐ واتّباع الرابط يُخرِج **صورة PNG فعليّة** من الرسّام على الخادم */
    public function test_following_the_link_downloads_a_real_png(): void
    {
        $admin = $this->grant(
            $this->makeUser(),
            'manual_rewards.list', 'manual_rewards.create', 'image_export.use',
        );
        $target = $this->makeUser('متدرّب');

        $html = $this->actingAs($admin)->post(route('admin.rewards.grant'), [
            'codes' => $target->code,
            'currency' => 'coins',
            'direction' => 'credit',
            'amount' => 120,
            'reason' => 'bonus',
            'confirm' => 1,
        ])->assertOk()->getContent();

        $this->assertSame(1, preg_match('~href="([^"]*/export/image[^"]*)"~', $html, $m), 'الرابط موجود في الصفحة');

        $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        $image = $this->actingAs($admin)->get($url)->assertOk();

        $this->assertSame('image/png', $image->headers->get('Content-Type'));
        // توقيع ملفّ PNG: \x89PNG\r\n\x1a\n — نفتح المخرَج ونقرأ بصمته لا امتداده
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $image->getContent());
        $this->assertStringContainsString('attachment;', (string) $image->headers->get('Content-Disposition'));
    }

    /**
     * ⭐ [2026-09-10] «صورته» (12.9) — الصفّ الممرَّر للرسّام لم يكن يحمل آيدي
     * المستلِم إطلاقًا (`u` مفقود من صفّ `BoardSnapshot`)، فتُرسَم دائرة أوّليّات
     * فارغة لا الأفاتار الحقيقيّ. المقارنة على **نفس الاسم بالضبط** بين مستلمَين
     * — أحدهما بأفاتار مرفوع والآخر بلا أفاتار — فالفرق البايتيّ الوحيد الممكن
     * هو الأفاتار نفسه لا أيّ نصٍّ آخر في البطاقة.
     */
    public function test_the_avatar_is_actually_drawn_on_the_card(): void
    {
        $admin = $this->grant(
            $this->makeUser(),
            'manual_rewards.list', 'manual_rewards.create', 'image_export.use',
        );

        $bare = User::create([
            'name' => 'نفس الاسم بالضبط',
            'email' => str()->random(8).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);

        $withAvatar = User::create([
            'name' => 'نفس الاسم بالضبط',
            'email' => str()->random(8).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);

        $image = imagecreatetruecolor(120, 120);
        imagefilledrectangle($image, 0, 0, 119, 119, imagecolorallocate($image, 200, 60, 30));
        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);
        Storage::disk('public')->put('avatars/reward-card-test.png', $binary);
        $withAvatar->forceFill(['avatar_path' => 'avatars/reward-card-test.png'])->save();

        $pngFor = function (User $target) use ($admin): string {
            $html = $this->actingAs($admin)->post(route('admin.rewards.grant'), [
                'codes' => $target->code,
                'currency' => 'coins',
                'direction' => 'credit',
                'amount' => 120,
                'reason' => 'bonus',
                'confirm' => 1,
            ])->assertOk()->getContent();

            preg_match('~href="([^"]*/export/image[^"]*)"~', $html, $m);
            $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');

            return $this->actingAs($admin)->get($url)->assertOk()->getContent();
        };

        $withoutAvatarPng = $pngFor($bare);
        $withAvatarPng = $pngFor($withAvatar);

        $this->assertNotSame(
            hash('sha256', $withoutAvatarPng),
            hash('sha256', $withAvatarPng),
            'بطاقتان بنفس الاسم بالضبط — أحدهما بأفاتار مرفوع — خرجتا بنفس بايتات الصورة: الأفاتار لا يُرسَم فعليًّا.',
        );
    }

    /** المحظور يُخفى لا يُعطَّل (2.15-أ-7): بلا `image_export.use` لا زرّ أصلًا */
    public function test_button_is_hidden_without_the_export_permission(): void
    {
        $admin = $this->grant($this->makeUser(), 'manual_rewards.list', 'manual_rewards.create');
        $target = $this->makeUser('متدرّب');

        $html = $this->actingAs($admin)->post(route('admin.rewards.grant'), [
            'codes' => $target->code,
            'currency' => 'coins',
            'direction' => 'credit',
            'amount' => 120,
            'reason' => 'bonus',
            'confirm' => 1,
        ])->assertOk()->getContent();

        // (السطر `[data-card-save]` يبقى في سكربت التنقّل — المقصود الرابط نفسه)
        $this->assertStringNotContainsString('data-card-save="', $html);
        $this->assertStringNotContainsString('/export/image', $html);
    }
}
