<?php

namespace Tests\Feature\Events;

use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\EventRegistration;

/**
 * ⭐ شهادة الفعاليّة تمرّ من محرّك الشهادات — لا من مسارٍ احتياطيّ (12.5).
 *
 * كان `CertificateBridge::delegate()` ينادي المحرّك بتوقيعٍ خاطئ فيفشل النداء
 * ويقع الكود على `record()` الاحتياطيّة: كودٌ عشوائيّ بلا تسلسل · **بلا لقطة
 * تصميم** · **بلا تدقيق ولا احتفال ولا إشعار** · وهاشٌ **بلا مفتاح** يقدر أيّ
 * أحدٍ يعرف الكود ورقم التسجيل أن يُنتجه بسطر PHP واحد — بينما صفحة التحقّق
 * تعرض الشهادة «سارية ومطابقة لسجلّنا».
 */
class EventCertificateEngineTest extends EventsTestCase
{
    private function attend(): EventRegistration
    {
        $user = $this->trainee();
        $event = $this->makeEvent([
            'starts_at' => now()->subMinutes(20),
            'ends_at' => now()->addMinutes(40),
            'attendance_code' => '135790',
            'certificate_type_id' => CertificateType::query()->where('key', 'event')->value('id'),
        ]);

        $this->actingAs($user)->post(route('events.register', $event->slug));
        $this->actingAs($user)->post(route('events.checkin', $event->slug), ['code' => '135790']);

        return EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    /** 12.5-ب: ترقيم متسلسل بلا تكرار ولا فجوات — لا `EVT-1HFXMVPB`. */
    public function test_event_certificate_uses_the_sequential_numbering(): void
    {
        $registration = $this->attend();
        $certificate = Certificate::query()->findOrFail($registration->certificate_id);

        $this->assertMatchesRegularExpression(
            '/^EVT-\d{4}-\d{6}$/u',
            (string) $certificate->code,
            'كود شهادة الفعاليّة لازم يتبع ترقيم المحرّك: بادئة + سنة + تسلسل',
        );
    }

    /** 12.5-ج: تجميد نسخة التصميم لحظة الإصدار — لا `template_snapshot = NULL`. */
    public function test_event_certificate_freezes_its_template_snapshot(): void
    {
        $registration = $this->attend();
        $certificate = Certificate::query()->findOrFail($registration->certificate_id);

        $this->assertIsArray($certificate->template_snapshot);
        $this->assertArrayHasKey('frozen_at', $certificate->template_snapshot);
        $this->assertNotEmpty($certificate->data_snapshot['certificate_name'] ?? null);
    }

    /**
     * 12.5-هـ: توقيعٌ **بمفتاح سرّ**. توقيعٌ بلا مفتاح ليس توقيعًا: من يعرف
     * المدخلات يُنتجه بنفسه. فالهاش لازم يكون HMAC بمفتاح التطبيق.
     */
    public function test_event_certificate_signature_uses_a_secret_key(): void
    {
        $registration = $this->attend();
        $certificate = Certificate::query()->findOrFail($registration->certificate_id);

        $this->assertNotEmpty($certificate->hash);

        // الهاش المكشوف القديم: sha256(code|registration_id) — لا يجوز أن يطابق
        $this->assertNotSame(
            hash('sha256', $certificate->code.'|'.$registration->id),
            $certificate->hash,
        );

        // وهو HMAC بمفتاح التطبيق فعلًا
        $this->assertSame(
            hash_hmac('sha256', implode('|', [
                $certificate->code,
                $certificate->user_id,
                'event',
                $certificate->issued_at->toIso8601String(),
            ]), (string) config('app.key')),
            $certificate->hash,
        );
    }

    /** 12.5-د: كلّ إصدارٍ في سجلّ التدقيق — لا إصدارٌ صامت. */
    public function test_event_certificate_is_written_to_the_audit_log(): void
    {
        $registration = $this->attend();

        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'certificate.issued')
                ->where('auditable_id', $registration->certificate_id)
                ->exists(),
        );
    }

    /** ومنع التكرار محفوظ: تشيك-إن متكرّر لا يُصدر شهادةً ثانية. */
    public function test_repeated_check_in_does_not_issue_a_second_certificate(): void
    {
        $registration = $this->attend();
        $event = $registration->event;

        $this->actingAs($registration->user)
            ->post(route('events.checkin', $event->slug), ['code' => '135790']);

        $this->assertSame(1, Certificate::query()->where('user_id', $registration->user_id)->count());
    }
}
