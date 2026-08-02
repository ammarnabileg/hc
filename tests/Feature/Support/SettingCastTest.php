<?php

namespace Tests\Feature\Support;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * تحويل قيمة الإعداد يتبع **النوع المعلَن** لا شكل النصّ المخزَّن (2.13).
 *
 * الخلل الذي تحرسه هذه الاختبارات: كان كلّ إعدادٍ قيمته «0» أو «1» يعود
 * بوليانًا مهما كان نوعه — فتذكرةٌ واحدة تعود `true` وصفرُ تذاكر يعود `false`.
 * ومعظم أرقام الاقتصاد في الدستور تقع في 0 و1 و2، فالعطب يمسّ قلب النظام.
 */
class SettingCastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('settings');
    }

    private function seedSetting(string $key, string $type, string $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['group' => 'testing', 'label_ar' => 'اختبار', 'type' => $type, 'value' => $value],
        );

        Cache::forget('settings');
    }

    public function test_numeric_setting_of_one_stays_a_number(): void
    {
        $this->seedSetting('testing.tickets', 'number', '1');

        $this->assertSame(1, setting('testing.tickets'));
    }

    /** الحالة التي كانت تُفرِغ الرقم من الشاشة: قرار إداريّ مشروع بألّا تُمنَح تذاكر. */
    public function test_numeric_setting_of_zero_stays_a_number(): void
    {
        $this->seedSetting('testing.tickets', 'number', '0');

        $this->assertSame(0, setting('testing.tickets'));
        $this->assertNotSame(false, setting('testing.tickets'));
    }

    public function test_boolean_setting_still_casts_to_boolean(): void
    {
        $this->seedSetting('testing.enabled', 'bool', '1');
        $this->assertTrue(setting('testing.enabled'));

        $this->seedSetting('testing.enabled', 'bool', '0');
        $this->assertFalse(setting('testing.enabled'));
    }

    public function test_string_setting_of_digits_is_not_coerced(): void
    {
        $this->seedSetting('testing.code', 'string', '0');

        $this->assertSame('0', setting('testing.code'));
    }

    public function test_json_setting_decodes_to_array(): void
    {
        $this->seedSetting('testing.tiers', 'json', '{"bronze":5}');

        $this->assertSame(['bronze' => 5], setting('testing.tiers'));
    }

    public function test_decimal_setting_keeps_its_fraction(): void
    {
        $this->seedSetting('testing.rate', 'number', '0.5');

        $this->assertSame(0.5, setting('testing.rate'));
    }

    public function test_missing_key_returns_the_default(): void
    {
        $this->assertSame(7, setting('testing.absent', 7));
    }
}
