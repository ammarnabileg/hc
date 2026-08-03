<?php

namespace Tests\Feature\Account;

use App\Models\ConsentRequest;
use App\Models\EmergencyContact;
use Illuminate\Support\Facades\Schema;

/**
 * حسابي ← الإعدادات (الدستور 24.5 · 2.17-ب · 13.4-م).
 */
class SettingsTest extends AccountTestCase
{
    public function test_settings_page_shows_the_four_groups_and_an_inner_search(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('الإعدادات')
            ->assertSee('دوّر على إعداد')
            ->assertSeeInOrder(['الحساب', 'المظهر', 'الصوت', 'جهة الطوارئ'])
            /*
             | ⛔ **توجّل الصوت وحده**: «**Toggle للصوت فقط** في **صفحة إعدادات
             | البروفايل** … **⛔ ولا يوجد Toggle للأنيميشن — الأنيميشن حاضر
             | دائمًا لأنّه روح المنصّة**» (2.3). فالصوت حاضر، والحركة بلا باب.
             */
            ->assertSee('صوت المنصّة')
            ->assertDontSee('حركة الواجهة')
            ->assertDontSee('motion_enabled', false);
    }

    /**
     * ⛔ لا بابَ لإطفاء الحركة — لا حقلَ يُحفَظ ولا سمةَ تُطبَع (2.3 · 2.14-ب).
     *
     * والحارس يقيس **غياب الباب** لا حسنَ سلوكه: حقلٌ مرفوض من الحفظ التلقائيّ،
     * وصفحةٌ خالية من سمة `data-motion`، وموديلٌ بلا خاصّيّة `motion_enabled`.
     */
    public function test_there_is_no_animation_toggle_anywhere(): void
    {
        $user = $this->trainee();

        // 1) لا حقل: الحفظ التلقائيّ لا يعرف الحقل أصلًا فيردّه
        $this->actingAs($user)
            ->patchJson(route('settings.field'), ['field' => 'motion_enabled', 'value' => '0'])
            ->assertStatus(422);

        // 2) لا سمة على `<html>` ولا استعلام وسائط نظام التشغيل
        $page = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $page->assertDontSee('prefers-reduced-motion', false);
        $this->assertDoesNotMatchRegularExpression('#<html[^>]*data-motion#', $page->getContent());

        // 3) لا عمود ولا خاصّيّة على الموديل
        $this->assertFalse(Schema::hasColumn('users', 'motion_enabled'));
        $this->assertNull($user->fresh()->motion_enabled);

        // 4) ولا قاعدة CSS تُصفّر الحركة في أيّ ملفّ من ملفّات الواجهة
        foreach ($this->frontendSources() as $file) {
            $body = (string) file_get_contents($file);

            $this->assertStringNotContainsString('@media (prefers-reduced-motion', $body, $file);
            $this->assertDoesNotMatchRegularExpression('#\[data-motion[^\]]*\]\s*[.\#\[*a-zA-Z]#', $body, $file);

            /*
             | 5) ولا مفتاحَ إعدادٍ يتفرّع عليه الكود ليطفئ الحركة.
             | `celebrations.animation_always_on` **قفلٌ معلَن** يراه المالك في
             | لوحته ولا يقرؤه كودٌ — ولو قُرِئ لصار توجّلًا بابه في الإعدادات.
             */
            $this->assertStringNotContainsString("setting('celebrations.animation_always_on'", $body, $file);
        }

        /*
         | 6) ولا قالبَ يقرأ `motion_enabled` — والقائمة المسموحة **واحد**:
         | `resources/views/exams/layouts/focus.blade.php` تحت يد إيجنت آخر الآن
         | (⛔ `resources/views/exams/**`) فلم يُلمَس. وما بقي فيه **سمةٌ خرساء**:
         | العمود مرفوع ولا قاعدة CSS تقرأ `data-motion` — والفحص (4) أعلاه هو
         | الذي يضمن خرسها. تُصفَّر هذه القائمة فور تحرّر الملفّ.
         */
        $readers = [];

        foreach ($this->frontendSources() as $file) {
            if (str_contains((string) file_get_contents($file), 'motion_enabled')) {
                $readers[] = str_replace(base_path().'/', '', $file);
            }
        }

        $this->assertSame(['resources/views/exams/layouts/focus.blade.php'], $readers,
            'قالبٌ جديد يقرأ `motion_enabled` — ولا وجود لتوجّل الأنيميشن (2.3).');
    }

    /** توجّل الصوت باقٍ ويعمل — «Toggle للصوت فقط» (2.3) */
    public function test_the_sound_toggle_still_works(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->patchJson(route('settings.field'), ['field' => 'sound_enabled', 'value' => '0'])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertFalse((bool) $user->fresh()->sound_enabled);

        $this->actingAs($user)
            ->patchJson(route('settings.field'), ['field' => 'sound_enabled', 'value' => '1'])
            ->assertOk();

        $this->assertTrue((bool) $user->fresh()->sound_enabled);
    }

    /** @return list<string> كلّ ملفّات الواجهة التي قد تحمل قاعدة حركة */
    private function frontendSources(): array
    {
        $files = [];

        foreach ([resource_path('css'), resource_path('js'), resource_path('views')] as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['css', 'js', 'php'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function test_autosave_saves_one_field_and_answers_with_the_saved_flag(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->patchJson(route('settings.field'), ['field' => 'name', 'value' => 'منى عبد الرحمن'])
            ->assertOk()
            ->assertJson(['saved' => true, 'message' => 'اتحفظ ✓']);

        $this->assertSame('منى عبد الرحمن', $user->fresh()->name);
    }

    public function test_autosave_refuses_a_field_outside_the_closed_list(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->patchJson(route('settings.field'), ['field' => 'status', 'value' => 'active'])
            ->assertStatus(422)
            ->assertJson(['saved' => false]);

        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_autosave_error_explains_in_arabic_and_keeps_the_value(): void
    {
        $user = $this->trainee(['name' => 'اسم أصليّ']);

        $response = $this->actingAs($user)
            ->patchJson(route('settings.field'), ['field' => 'name', 'value' => 'أ'])
            ->assertStatus(422);

        // رسالة الخطأ = ماذا حدث + ماذا تفعل، بلا أكواد تقنيّة (2.17-ب)
        $this->assertStringNotContainsString('validation.', (string) $response->json('message'));
        $this->assertSame('اسم أصليّ', $user->fresh()->name);
    }

    public function test_changing_email_invalidates_active_contact_consents(): void
    {
        $owner = $this->trainee(['email' => 'before@test.local']);
        $requester = $this->trainee();

        $consent = ConsentRequest::create([
            'requester_id' => $requester->id,
            'owner_id' => $owner->id,
            'field' => 'email',
            'status' => 'granted',
            'request_expires_at' => now()->subDay(),
            'granted_at' => now()->subDay(),
            'consent_expires_at' => now()->addDays(29),
        ]);

        $response = $this->actingAs($owner)
            ->patchJson(route('settings.field'), ['field' => 'email', 'value' => 'after@test.local'])
            ->assertOk();

        // البيانات الجديدة لا ترث موافقة قديمة (13.4-م)
        $this->assertSame('expired', $consent->fresh()->status);
        $this->assertStringContainsString('وقفنا عرض بياناتك', $response->json('message'));
    }

    public function test_settings_page_warns_about_the_effect_on_contact_consents(): void
    {
        $owner = $this->trainee();
        $requester = $this->trainee();

        ConsentRequest::create([
            'requester_id' => $requester->id, 'owner_id' => $owner->id, 'field' => 'phone',
            'status' => 'granted', 'request_expires_at' => now()->subDay(),
            'granted_at' => now()->subDay(), 'consent_expires_at' => now()->addDays(29),
        ]);

        $this->actingAs($owner)->get(route('settings.index'))
            ->assertOk()
            ->assertViewHas('activeConsents', 1)
            ->assertSee('هيتوقف عرض بياناتك لـ');
    }

    public function test_emergency_contact_is_optional_and_capped(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->post(route('settings.emergency.store'), [
            'name' => 'سلوى عبد الرحمن', 'phone' => '+201000000900', 'relation' => 'الوالدة',
        ])->assertRedirect();

        $this->assertSame(1, EmergencyContact::where('user_id', $user->id)->count());

        $contact = EmergencyContact::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->delete(route('settings.emergency.destroy', $contact))->assertRedirect();
        $this->assertSame(0, EmergencyContact::where('user_id', $user->id)->count());
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->post(route('settings.password'), [
            'current_password' => 'wrong-password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->post(route('settings.password'), [
            'current_password' => 'secret-password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertSessionHasNoErrors();
    }

    /**
     * 12.6-أ — قناة البريد **يملكها صاحبها**.
     *
     * العمود `email_optout_at` كان مبنيًّا ومحترَمًا على الخادم بلا مكانٍ واحد
     * يحرّره منه المستخدم — أي «تفضيل» لا يملكه صاحبه. والحقل ظاهريّ: يُخزَّن
     * **لحظةَ** الإيقاف لا رايةً، فيُعرَف متى أوقفها لا أنّه أوقفها فقط.
     */
    public function test_the_user_owns_the_email_channel_from_his_own_settings(): void
    {
        $user = $this->trainee();

        $this->assertNull($user->email_optout_at, 'القناة مقفولة على مستخدمٍ جديد.');

        $this->actingAs($user)
            ->patchJson(route('settings.field'), ['field' => 'email_channel', 'value' => '0'])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertNotNull($user->refresh()->email_optout_at, 'الإيقاف ما اتخزّنش.');

        // والرجوع يمحو اللحظة — فالقرار ليس طريقًا في اتّجاهٍ واحد
        $this->actingAs($user)
            ->patchJson(route('settings.field'), ['field' => 'email_channel', 'value' => '1'])
            ->assertOk();

        $this->assertNull($user->refresh()->email_optout_at);
    }

    /** والشاشة تعرض الخيار بحالته الحاليّة — وإلّا كان المسار بلا باب. */
    public function test_the_settings_screen_shows_the_email_channel_choice(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('email_channel', false)
            ->assertSee('رسايل البريد');
    }
}
