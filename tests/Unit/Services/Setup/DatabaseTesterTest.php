<?php

namespace Tests\Unit\Services\Setup;

use App\Models\Setting;
use App\Services\Setup\DatabaseTester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبار الاتّصال بقاعدة البيانات وقت التنصيب (2.2) — العطل الحقيقيّ الذي
 * دفع هذا الملفّ: مستخدمٌ على VPS خاصّ أبلغ أنّ خطوة قاعدة البيانات
 * «بتفضل معلّقة من غير ردّ» عند الضغط على [اختبار الاتّصال]. السبب المرجَّح:
 * `PDO::ATTR_TIMEOUT` لا يضبط مرحلة الاتّصال الأولى (TCP connect) بثباتٍ على
 * كلّ نظام، فجدارٌ ناريّ يُسقط الحزم صامتًا يترك PHP معلّقًا لمهلة النظام
 * الافتراضيّة (قد تتجاوز الدقيقة) — فتعلَّق صفحة الويب كلّها بلا ردّ ولا رسالة.
 */
class DatabaseTesterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // مهلة قصيرة تكفي الاختبار بلا انتظار طويل
        Setting::create(['key' => 'setup.database.timeout_seconds', 'group' => 'setup', 'label_ar' => 'x', 'type' => 'number', 'value' => '1']);
    }

    /**
     * ⭐ العطل نفسه: عنوانٌ لا يردّ إطلاقًا (محاكاة جدارٍ ناريّ يُسقط الحزم صامتًا)
     * — والفحص يجب أن يرجع خلال المهلة المضبوطة بالضبط لا أن يعلَّق للأبد.
     */
    public function test_an_address_that_never_responds_fails_within_the_configured_timeout_not_forever(): void
    {
        $started = microtime(true);

        $result = app(DatabaseTester::class)->test([
            // عنوان IP خاصّ غير موجّه — الحزم تُسقَط صامتًا لا تُرفَض صراحةً
            'db_host' => '10.255.255.1',
            'db_port' => '3306',
            'db_database' => 'x',
            'db_username' => 'x',
            'db_password' => 'x',
        ]);

        $elapsed = microtime(true) - $started;

        $this->assertFalse($result['ok']);
        $this->assertLessThan(5, $elapsed, 'المهلة المضبوطة ثانية واحدة — أيّ وقتٍ أطول بكثير يعني تعليقًا لا مهلة حقيقيّة.');
        $this->assertStringContainsString('10.255.255.1', $result['message']);
    }

    /** منفذٌ مقفول على خادمٍ يردّ فعلًا (Connection refused) — رسالةٌ مختلفة عن الإسقاط الصامت */
    public function test_a_closed_port_on_a_reachable_host_reports_connection_refused(): void
    {
        $result = app(DatabaseTester::class)->test([
            'db_host' => '127.0.0.1',
            // منفذ 1 محجوز للنظام ولا يستمع عليه أحد فعليًّا
            'db_port' => '1',
            'db_database' => 'x',
            'db_username' => 'x',
            'db_password' => 'x',
        ]);

        $this->assertFalse($result['ok']);
        // نصّ الرفض الصريح ("جرّب 127.0.0.1...") لا نصّ الإسقاط الصامت ("خلال N ثانية")
        $this->assertStringContainsString('127.0.0.1', $result['message']);
        $this->assertStringNotContainsString('ثانية', $result['message']);
    }
}
