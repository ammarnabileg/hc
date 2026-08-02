<?php

namespace Tests\Feature\Ui;

use App\Models\Country;
use App\Models\Governorate;
use App\Models\ImageTemplate;
use App\Models\NameParticle;
use App\Services\Images\BoardSnapshot;
use App\Services\Images\ShortName;
use Illuminate\Support\Facades\URL;

/**
 * زرّ [استخراج كصورة] لكلّ لوحات المنصّة (الدستور 12.14-هـ · 12.14-ج · 12.14-ز).
 */
class ExportImageTest extends UiTestCase
{
    private function signed(array $rows, array $options = []): string
    {
        $snapshot = new BoardSnapshot('leaderboard', 'الليدر بورد', 'آخر 30 يوم', $rows);

        return URL::signedRoute('export.image', ['d' => $snapshot->encode()]).
            ($options ? '&'.http_build_query($options) : '');
    }

    public function test_export_route_renders_a_png_for_a_permitted_user(): void
    {
        $user = $this->exporter(['name' => 'محمد أحمد علي']);

        $response = $this->actingAs($user)->get($this->signed([
            ['rank' => 1, 'u' => $user->id, 'name' => $user->name, 'value' => '1,200 XP', 'me' => true],
        ]));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        // ترويسة PNG — دليلٌ على أنّ الرسم تمّ على الخادم فعلًا (12.14-و)
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }

    public function test_export_is_hidden_and_forbidden_without_the_permission(): void
    {
        // ⭐ بلا صلاحيّة = مخفيّ فعلًا في الواجهة (2.15-أ-7) وممنوع على المسار (12.2.1)
        $user = $this->trainee();

        $this->actingAs($user)
            ->get($this->signed([['rank' => 1, 'u' => $user->id, 'value' => '10']]))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('achievements.leaderboard'))
            ->assertOk()
            ->assertDontSee('استخراج كصورة');
    }

    public function test_leaderboard_screen_shows_the_export_button_for_a_permitted_user(): void
    {
        $this->actingAs($this->exporter())
            ->get(route('achievements.leaderboard'))
            ->assertOk()
            ->assertSee('استخراج كصورة');
    }

    public function test_a_tampered_payload_is_rejected(): void
    {
        $user = $this->exporter();
        $url = $this->signed([['rank' => 1, 'u' => $user->id, 'value' => '10']]);

        // تزوير الحمولة بعد التوقيع ⟵ 403، فلا تُنشَر لوحة ملفَّقة باسم المنصّة
        $tampered = preg_replace('/d=[^&]+/', 'd=z'.BoardSnapshot::base64url('مزوّر'), $url);

        $this->actingAs($user)->get($tampered)->assertForbidden();
    }

    public function test_options_do_not_break_the_signature(): void
    {
        $user = $this->exporter();

        $this->actingAs($user)
            ->get($this->signed(
                [['rank' => 1, 'u' => $user->id, 'value' => '10']],
                ['size' => 'story', 'top' => 'me', 'avatars' => '0', 'frame' => 'below'],
            ))
            ->assertOk();
    }

    public function test_governorate_is_never_hidden_in_the_export(): void
    {
        $country = Country::firstOrCreate(['iso2' => 'EG'], [
            'name_ar' => 'مصر', 'name_en' => 'Egypt', 'phone_code' => '20',
        ]);

        $governorate = Governorate::firstOrCreate(
            ['country_id' => $country->id, 'name_ar' => 'الإسكندريّة'],
            ['name_en' => 'Alexandria'],
        );

        $user = $this->exporter([
            'name' => 'عبد الرحمن محمد علي',
            'country_id' => $country->id,
            'governorate_id' => $governorate->id,
        ]);

        // الحمولة تحاول إخفاء المحافظة — والخادم يكتبها من المصدر رغمًا عنها (12.14-د)
        $snapshot = new BoardSnapshot('leaderboard', 'لوحة', '', [
            ['rank' => 1, 'u' => $user->id, 'name' => $user->name, 'gov' => '', 'value' => '10'],
        ]);

        $rows = $snapshot->normalizedRows(1);

        $this->assertSame('الإسكندريّة', $rows[0]['gov']);
    }

    public function test_display_name_keeps_particles_with_the_word_after_them(): void
    {
        ShortName::flush();

        // ⭐ أدوات الاسم جزءٌ من الكلمة التي تليها فلا تُحسَب وحدةً (12.14-ج)
        $this->assertSame('عبد الرحمن محمد', ShortName::of('عبد الرحمن محمد علي', 2));
        $this->assertSame('أبو بكر الصدّيق', ShortName::of('أبو بكر الصدّيق', 2));
        $this->assertSame('محمد أحمد', ShortName::of('محمد أحمد علي', 2));
        $this->assertSame('abd elrahman mohamed', ShortName::of('abd elrahman mohamed ali', 2));
    }

    public function test_particle_list_is_editable_so_the_rule_follows_the_culture(): void
    {
        NameParticle::create(['particle' => 'دي', 'locale' => 'ar', 'is_active' => true]);
        ShortName::flush();

        $this->assertSame('دي جمال محمد', ShortName::of('دي جمال محمد علي', 2));
    }

    public function test_admin_only_template_is_ignored_for_a_plain_exporter(): void
    {
        $user = $this->exporter();

        $template = ImageTemplate::create([
            'name' => 'قالب إداريّ',
            'purpose' => 'marketing',
            'width_px' => 1080,
            'height_px' => 1080,
            'audience' => 'admin',
            'is_active' => true,
            'layers' => [],
        ]);

        // القالب الخاصّ بالإدارة لا يُطبَّق لمن ليس منها — والصورة تخرج بتصميم المنصّة
        $this->actingAs($user)
            ->get($this->signed([['rank' => 1, 'u' => $user->id, 'value' => '10']], ['template' => $template->id]))
            ->assertOk();
    }
}
