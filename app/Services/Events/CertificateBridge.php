<?php

namespace App\Services\Events;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\EventRegistration;
use App\Services\Admin\Content\ContentAudit;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Facades\Route;

/**
 * جسر شهادة الفعاليّة (8 · 12.5 · 13.3).
 *
 * ⭐ **لا إصدار خارج المحرّك.** شهادة حضور الفعاليّة نوعُ شهادةٍ يُضبَط في «إدارة
 * الشهادات» (12.5) لا في فورم الفعاليّة، ولذلك يمرّ إصدارها من `CertificateIssuer`
 * نفسه الذي تمرّ منه كلّ الشهادات — فتأخذ **ترقيمها المتسلسل بلا فجوات** (12.5-ب)
 * و**لقطة التصميم المجمّدة** (12.5-ج) و**التوقيع بمفتاح التطبيق** (12.5-هـ)
 * ولحظة الذروة والإشعار والتدقيق.
 *
 * وكان هنا مسارٌ احتياطيّ يبتلع أيّ خطأ ويكتب سجلًّا يدويًّا بكودٍ عشوائيّ وهاشٍ
 * **بلا مفتاح** (`sha256(code|registration_id)`) — أي توقيعٌ يقدر أيّ أحدٍ يعرف
 * الكود ورقم التسجيل أن يُنتجه بنفسه، وصفحةُ التحقّق تعرضه «ساريًا ومطابقًا».
 * فحُذِف: الفشل يظهر ولا يُخفى خلف شهادةٍ قابلة للتزوير.
 */
class CertificateBridge
{
    public function __construct(
        private readonly CertificateIssuer $issuer,
        private readonly ContentAudit $audit,
    ) {}

    /** الشهادة تُفتَح بالحضور المؤكَّد فقط — وتُصدَر مرّة واحدة */
    public function issueForRegistration(EventRegistration $registration): ?Certificate
    {
        if ($registration->certificate_id) {
            return $registration->certificate;
        }

        $event = $registration->event;
        $type = $this->type($event?->certificate_type_id);

        if (! $type || ! $registration->user) {
            return null;
        }

        $certificate = $this->issuer->issue(
            user: $registration->user,
            typeKey: $type->key,
            subject: $event,
            data: [
                'event_title' => $event?->title_ar,
                'attended_at' => $registration->attended_at?->toIso8601String(),
                'ticket_code' => $registration->ticket_code,
            ],
            source: 'auto',
        );

        if (! $certificate) {
            return null;
        }

        $registration->forceFill(['certificate_id' => $certificate->id])->save();

        // لحظة الذروة + الإشعار + التدقيق — كما في أيّ إصدارٍ آخر (12.5-ج · 12.5-د)
        $this->issuer->claimCelebration($certificate);
        $this->audit->record($certificate, 'certificate.issued', [], [
            'type' => $type->key,
            'code' => $certificate->code,
            'event_id' => $registration->event_id,
            'registration_id' => $registration->id,
        ]);
        $this->notify($certificate);

        return $certificate;
    }

    private function type(?int $typeId): ?CertificateType
    {
        if ($typeId && $type = CertificateType::query()->where('id', $typeId)->where('is_active', true)->first()) {
            return $type;
        }

        // نوع الشهادة الافتراضيّ للفعاليّات — إعداد لا رقم محروق (2.13)
        return CertificateType::query()
            ->where('key', (string) setting('events.certificate.default_type_key', 'event'))
            ->where('is_active', true)
            ->first();
    }

    private function notify(Certificate $certificate): void
    {
        if (! setting('certificates.issue.notify_user', true)) {
            return;
        }

        Notifier::send(
            user: $certificate->user,
            category: 'certificate',
            title: (string) setting('certificates.issue.notice_title', 'مبروك — صدرت شهادتك 🎓'),
            body: (string) ($certificate->data_snapshot['certificate_name'] ?? null),
            url: Route::has('verify.certificate')
                ? route('verify.certificate', ['code' => $certificate->code])
                : null,
        );
    }
}
