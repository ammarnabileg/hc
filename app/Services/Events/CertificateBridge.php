<?php

namespace App\Services\Events;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\EventRegistration;
use Illuminate\Support\Str;
use Throwable;

/**
 * جسر الشهادات (8 · 12.5).
 *
 * السبب: شهادة حضور الفعاليّة **نوع شهادة يُضبَط في إدارة الشهادات لا في فورم الفعاليّة** (13.3)،
 * فإن وُجد مجال الشهادات فهو من يُصدر، وإلّا نسجّل الاستحقاق كسجلّ شهادة بلا أكثر.
 */
class CertificateBridge
{
    private const ISSUERS = [
        'App\Services\Certificates\CertificateIssuer',
        'App\Services\Certificates\IssueCertificate',
        'App\Services\Certificates\CertificateService',
    ];

    /** الشهادة تُفتَح بالحضور المؤكَّد فقط — وتُصدَر مرّة واحدة */
    public function issueForRegistration(EventRegistration $registration): ?Certificate
    {
        if ($registration->certificate_id) {
            return $registration->certificate;
        }

        $event = $registration->event;
        $type = $this->type($event?->certificate_type_id);

        if (! $type) {
            return null;
        }

        $certificate = $this->delegate($registration, $type) ?? $this->record($registration, $type);

        if ($certificate) {
            $registration->forceFill(['certificate_id' => $certificate->id])->save();
        }

        return $certificate;
    }

    private function type(?int $typeId): ?CertificateType
    {
        if ($typeId && $type = CertificateType::find($typeId)) {
            return $type;
        }

        // نوع الشهادة الافتراضيّ للفعاليّات — إعداد لا رقم محروق (2.13)
        return CertificateType::query()
            ->where('key', (string) setting('events.certificate.default_type_key', 'event'))
            ->where('is_active', true)
            ->first();
    }

    private function delegate(EventRegistration $registration, CertificateType $type): ?Certificate
    {
        foreach (self::ISSUERS as $class) {
            if (! class_exists($class)) {
                continue;
            }

            try {
                $service = app($class);

                foreach (['issue', 'issueFor', 'handle'] as $method) {
                    if (method_exists($service, $method)) {
                        $result = $service->{$method}($registration->user, $type, $registration->event);

                        return $result instanceof Certificate ? $result : null;
                    }
                }
            } catch (Throwable) {
                // اختلاف توقيع مجال الشهادات لا يمنع تسجيل الاستحقاق أدناه
                return null;
            }
        }

        return null;
    }

    /** تسجيل الاستحقاق: سجلّ شهادة بكود وهاش للتحقّق العامّ (8.1) */
    private function record(EventRegistration $registration, CertificateType $type): Certificate
    {
        $prefix = $type->numbering_prefix ?: (string) setting('events.certificate.code_prefix', 'EVT');

        do {
            $code = $prefix.'-'.Str::upper(Str::random(8));
        } while (Certificate::where('code', $code)->exists());

        return Certificate::create([
            'code' => $code,
            'hash' => hash('sha256', $code.'|'.$registration->id),
            'user_id' => $registration->user_id,
            'certificate_type_id' => $type->id,
            'subject_type' => $registration->event ? $registration->event::class : null,
            'subject_id' => $registration->event_id,
            'language' => 'ar',
            'source' => 'auto',
            'issued_at' => now(),
            'status' => 'valid',
            'data_snapshot' => [
                'event_title' => $registration->event?->title_ar,
                'attended_at' => $registration->attended_at?->toIso8601String(),
                'ticket_code' => $registration->ticket_code,
            ],
        ]);
    }
}
