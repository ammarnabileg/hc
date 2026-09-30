<?php

namespace Tests\Feature\Learning;

use App\Models\Permission;
use App\Models\User;
use App\Support\Access\AccessEngine;
use App\Services\Images\BoardSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * لقطة إنجاز جاهزة (الفكرة #22): بعد الإنجاز يظهر زرّ مشاركة يفتح بطاقةً
 * بالاسم والإنجاز والتاريخ من استوديو الصور، بحمولةٍ موقَّعة، ولا يظهر لمن لا
 * يملك صلاحيّة الاستخراج أو لإنجازٍ خفيف.
 */
class CelebrationShareSnapshotTest extends LearningTestCase
{
    private function allowExport(User $user): User
    {
        $permission = Permission::firstOrCreate(['key' => 'image_export.use'], [
            'resource' => 'image_export', 'action' => 'use', 'group' => 'اختبار', 'label_ar' => 'image_export.use',
            'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
        ]);
        DB::table('permission_user')->insertOrIgnore([
            'permission_id' => $permission->id, 'user_id' => $user->id, 'membership_id' => null,
            'scope' => 'ALL', 'effect' => 'allow', 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(AccessEngine::class)->forget($user);

        return $user;
    }

    private function render(array $celebration): string
    {
        return view('learning.partials.celebration', ['celebration' => $celebration])->render();
    }

    public function test_the_share_link_is_a_signed_snapshot_with_name_achievement_and_date(): void
    {
        $user = $this->allowExport($this->trainee('سلمى'));
        $this->actingAs($user);

        $html = $this->render(['tier' => 3, 'key' => 'certificate.issued', 'label' => 'إصدار شهادة', 'message' => 'مبروك الشهادة', 'sound' => false, 'sound_path' => null]);

        $this->assertStringContainsString('data-celebration-share', $html);
        preg_match('/<a href="([^"]+)" target="_blank" rel="noopener" data-celebration-share/', $html, $m);
        $url = html_entity_decode($m[1]);
        $this->assertStringContainsString('signature=', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $snapshot = BoardSnapshot::decode((string) $query['d']);

        $this->assertSame('card', $snapshot->kind);
        $this->assertSame('مبروك الشهادة', $snapshot->title);
        $this->assertStringContainsString(now()->format('Y/m/d'), $snapshot->subtitle);
        $this->assertSame('سلمى', $snapshot->rows[0]['name']);
        $this->assertSame('إصدار شهادة', $snapshot->rows[0]['value']);
        $this->assertSame(0, $snapshot->rows[0]['rank'], 'بطاقة إنجاز بلا رقم ترتيب');
        $this->assertSame(0, $snapshot->normalizedRows(1)[0]['rank'], 'التطبيع يحترم الصفر ولا يرقّم الصفّ');

        // الرابط الموقَّع يفتح فعلًا صورة
        $this->get($url)->assertOk()->assertHeader('content-type', 'image/png');
    }

    public function test_a_trainee_without_the_general_permission_gets_a_self_only_card_link(): void
    {
        $plain = $this->trainee('عادي');
        $this->actingAs($plain);

        $html = $this->render(['tier' => 3, 'key' => 'certificate.issued', 'label' => 'شهادة', 'message' => 'مبروك', 'sound' => false, 'sound_path' => null]);
        preg_match('/<a href="([^"]+)" target="_blank" rel="noopener" data-celebration-share/', $html, $m);
        $url = html_entity_decode($m[1] ?? '');

        $this->assertStringContainsString('/export/my-achievement', $url);
        $this->get($url)->assertOk()->assertHeader('content-type', 'image/png');

        // بطاقة بصفّ شخصٍ آخر على المسار الذاتيّ تُرفَض ولو كان التوقيع صحيحًا
        $other = $this->trainee('غيري');
        $forged = new BoardSnapshot('card', 'x', 'y', [['rank' => 1, 'u' => $other->id, 'name' => 'غيري', 'value' => 'z']]);
        $this->get(URL::signedRoute('export.self-card', ['d' => $forged->encode()]))->assertForbidden();

        // ومسار الاستخراج العامّ يبقى مقفولًا على مَن لا يملك صلاحيّته
        $this->get(URL::signedRoute('export.image', ['d' => $forged->encode()]))->assertForbidden();
    }

    public function test_no_share_link_for_light_events_or_when_the_toggle_is_off(): void
    {
        $this->actingAs($this->trainee('ب'));
        $this->assertStringNotContainsString('data-celebration-share', $this->render(['tier' => 1, 'key' => 'lesson.completed', 'label' => 'x', 'message' => 'y', 'sound' => false, 'sound_path' => null]));
        $this->assertStringContainsString('data-celebration-share', $this->render(['tier' => 2, 'key' => 'course.completed', 'label' => 'x', 'message' => 'y', 'sound' => false, 'sound_path' => null]));

        \App\Models\Setting::query()->firstOrCreate(['key' => 'learning.celebration.share_snapshot_enabled'], ['group' => 'learning', 'label_ar' => 'x', 'type' => 'bool', 'value' => '1', 'default_value' => '1']);
        \App\Models\Setting::query()->where('key', 'learning.celebration.share_snapshot_enabled')->update(['value' => '0']);
        \Illuminate\Support\Facades\Cache::forget('settings');
        $this->assertStringNotContainsString('data-celebration-share', $this->render(['tier' => 2, 'key' => 'course.completed', 'label' => 'x', 'message' => 'y', 'sound' => false, 'sound_path' => null]));
    }
}
