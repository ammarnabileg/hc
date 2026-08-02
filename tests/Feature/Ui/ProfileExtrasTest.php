<?php

namespace Tests\Feature\Ui;

use App\Models\Badge;
use App\Models\BadgeUser;
use App\Models\Setting;
use App\Services\Images\AvatarProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * تعزيزات البروفايل (الدستور 10): النبذة · زرّ المشاركة · قسم الشارات،
 * ومقاسات الأفاتار الثلاثة مع القصّ المربّع (2.7).
 */
class ProfileExtrasTest extends UiTestCase
{
    // ------------------------------------------------------------------ النبذة

    public function test_owner_can_edit_the_bio_with_autosave(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->patchJson(route('profile.bio'), ['bio' => 'بحبّ التعلّم وبساعد اللي حواليّا.'])
            ->assertOk()
            ->assertJson(['saved' => true, 'label' => 'اتحفظ ✓']);

        $this->assertSame('بحبّ التعلّم وبساعد اللي حواليّا.', $user->fresh()->bio);
    }

    public function test_bio_shows_on_the_public_profile(): void
    {
        $owner = $this->trainee(['code' => 'UBIO0001']);
        $owner->forceFill(['bio' => 'مصمّم جرافيك ومتطوّع.'])->save();

        $this->actingAs($this->trainee())
            ->get(route('u.profile', ['code' => 'UBIO0001']))
            ->assertOk()
            ->assertSee('مصمّم جرافيك ومتطوّع.');
    }

    public function test_bio_length_limit_is_a_setting_and_the_error_says_what_to_do(): void
    {
        Setting::where('key', 'profile.bio.max_chars')->update(['value' => '20']);
        Cache::forget('settings');

        $this->actingAs($this->trainee())
            ->patchJson(route('profile.bio'), ['bio' => str_repeat('ا', 40)])
            ->assertStatus(422)
            ->assertJsonFragment(['bio' => ['النبذة أطول من 20 حرف — اختصرها شويّة وجرّب تاني.']]);
    }

    // ------------------------------------------------------------------ المشاركة

    public function test_share_popup_holds_the_four_networks_and_the_public_link(): void
    {
        $owner = $this->trainee(['code' => 'USHARE01']);

        $this->actingAs($owner)
            ->get(route('profile.me'))
            ->assertOk()
            ->assertSee('مشاركة الحساب')
            ->assertSee('تيليجرام')
            ->assertSee('فيسبوك')
            ->assertSee('واتساب')
            ->assertSee($owner->profileUrl());
    }

    // ------------------------------------------------------------------ الشارات

    public function test_badges_section_shows_earned_and_locked_badges(): void
    {
        $earned = Badge::create([
            'key' => 'first_course', 'name_ar' => 'أوّل تدريب',
            'condition_text_ar' => 'أكمل أوّل تدريب ليك', 'is_active' => true,
        ]);

        Badge::create([
            'key' => 'ten_courses', 'name_ar' => 'عشرة تدريبات',
            'condition_text_ar' => 'أكمل عشر تدريبات', 'is_active' => true,
        ]);

        $owner = $this->trainee(['code' => 'UBADGE01']);
        BadgeUser::create(['user_id' => $owner->id, 'badge_id' => $earned->id, 'awarded_at' => now()]);

        $this->actingAs($owner)
            ->get(route('profile.me'))
            ->assertOk()
            ->assertSee('الشارات')
            ->assertSee('أوّل تدريب')
            // غير المفعّلة تظهر **مقفولة** لا مخفيّة (10)
            ->assertSee('عشرة تدريبات')
            ->assertSee('مقفولة', false);
    }

    public function test_ambassador_title_shows_on_the_profile(): void
    {
        $owner = $this->trainee(['code' => 'UAMB0001']);
        $owner->forceFill(['ambassador_title' => 'سفير ذهبيّ'])->save();

        Badge::create([
            'key' => 'any', 'name_ar' => 'شارة', 'condition_text_ar' => 'شرط', 'is_active' => true,
        ]);

        $this->actingAs($owner)->get(route('profile.me'))->assertOk()->assertSee('سفير ذهبيّ');
    }

    // ------------------------------------------------------------------ الأفاتار (2.7)

    public function test_avatar_upload_generates_three_square_sizes(): void
    {
        Storage::fake('public');

        $user = $this->trainee();

        $this->actingAs($user)
            ->post(route('settings.avatar'), [
                'avatar' => UploadedFile::fake()->image('me.jpg', 900, 600),
            ])
            ->assertRedirect();

        $sizes = $user->fresh()->avatar_sizes;

        $this->assertSame([500, 150, 50], array_map('intval', array_keys($sizes)));

        foreach ($sizes as $size => $path) {
            Storage::disk('public')->assertExists($path);

            // ⭐ قصّ مربّع إجباريّ: الطول = العرض في كلّ نسخة (2.7)
            [$width, $height] = getimagesize(Storage::disk('public')->path($path));
            $this->assertSame((int) $size, $width);
            $this->assertSame($width, $height);
        }
    }

    public function test_each_context_gets_the_size_it_needs(): void
    {
        $user = $this->trainee();
        $user->forceFill(['avatar_sizes' => [
            '500' => 'avatars/1/500.png', '150' => 'avatars/1/150.png', '50' => 'avatars/1/50.png',
        ]])->save();

        $processor = app(AvatarProcessor::class);

        // كارت 40 بكسل لا يحمّل 500 بكسل — الأداء أولويّة عليا (2.7)
        $this->assertSame('avatars/1/50.png', $processor->pick($user->fresh(), 40));
        $this->assertSame('avatars/1/150.png', $processor->pick($user->fresh(), 120));
        $this->assertSame('avatars/1/500.png', $processor->pick($user->fresh(), 400));
    }
}
