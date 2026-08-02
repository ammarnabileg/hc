<?php

namespace App\Services\Certificates;

use App\Models\CelebrationConsumption;
use App\Models\CelebrationEvent;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * إصدار الشهادات (8 · 12.5-ج): كود فريد + توقيع رقميّ (Hash)
 * + **تجميد نسخة التصميم** (فلو تغيّر القالب لاحقًا تبقى القديمة بشكلها)
 * + لحظة ذروة للمستخدم (2.14-3).
 */
class CertificateIssuer
{
    public function __construct(
        private readonly CertificateNumber $numbers,
        private readonly CertificateRenderer $renderer,
    ) {}

    /**
     * يُصدر شهادةً أو يعيد القائمة إن كانت صادرةً بالفعل (منع التكرار — 12.5-ج).
     */
    public function issue(
        User $user,
        string $typeKey,
        ?Model $subject = null,
        array $data = [],
        string $source = 'auto',
        ?string $language = null,
        ?User $issuedBy = null,
    ): ?Certificate {
        $type = CertificateType::query()->where('key', $typeKey)->where('is_active', true)->first();

        if (! $type) {
            return null;
        }

        $existing = $this->existing($user, $type, $subject);

        if ($existing) {
            return $existing;
        }

        $language ??= $type->lang_ar_enabled ? 'ar' : 'en';

        return DB::transaction(function () use ($user, $type, $subject, $data, $source, $language, $issuedBy) {
            $code = $this->numbers->next($type);
            $issuedAt = now();

            $certificate = new Certificate([
                'code' => $code,
                'hash' => $this->hash($code, $user, $type, $issuedAt->toIso8601String()),
                'user_id' => $user->id,
                'certificate_type_id' => $type->id,
                'language' => $language,
                'template_snapshot' => $this->templateSnapshot($type, $language),
                'data_snapshot' => $this->dataSnapshot($user, $type, $subject, $data, $code, $issuedAt),
                'source' => $source,
                'issued_at' => $issuedAt,
                'status' => 'valid',
                'issued_by' => $issuedBy?->id,
            ]);

            if ($subject) {
                $certificate->subject()->associate($subject);
            }

            $certificate->save();

            return $certificate;
        });
    }

    /**
     * ⭐ 13.4-ق: بمجرّد دخول العائد امتحانًا أحدث تصير شهادته التأهيليّة القديمة **«منتهية»**.
     * و«منتهية» ليست «ملغاة»: لا تُمسَح ولا تُخفى — تبقى بتاريخ إصدارها وتاريخ انتهاء العمل بها،
     * **والإلغاء يبقى للتزوير المثبَت وحده**. والأثر محصورٌ في النوع المذكور فقط.
     */
    public function expireForNewerExam(User $user, string $typeKey): int
    {
        $type = CertificateType::query()->where('key', $typeKey)->first();

        if (! $type) {
            return 0;
        }

        $certificates = Certificate::query()
            ->where('user_id', $user->id)
            ->where('certificate_type_id', $type->id)
            ->where('status', 'valid')
            ->get();

        foreach ($certificates as $certificate) {
            $certificate->update([
                'status' => 'expired',
                'expired_at' => now(),
                'expired_reason' => (string) setting(
                    'certificates.expired.reason_newer_exam',
                    'انتهى العمل بها بعد دخول صاحبها امتحانًا أحدث',
                ),
            ]);

            $this->renderer->forget($certificate);
        }

        return $certificates->count();
    }

    /**
     * احتفال الذروة عند إصدار الشهادة (2.14-3): مرّة واحدة لكلّ حدث تُعلَّم مستهلَكةً
     * Server-side، وبحدٍّ يوميّ كي تبقى الذروة ذروةً.
     */
    public function claimCelebration(Certificate $certificate): bool
    {
        $event = CelebrationEvent::query()
            ->where('key', (string) setting('certificates.celebration.key', 'certificate.issued'))
            ->where('is_active', true)
            ->first();

        if (! $event) {
            return false;
        }

        $already = CelebrationConsumption::query()
            ->where('user_id', $certificate->user_id)
            ->where('celebration_event_id', $event->id)
            ->where('reference_type', $certificate->getMorphClass())
            ->where('reference_id', $certificate->id)
            ->exists();

        if ($already) {
            return false;
        }

        $todayPeaks = CelebrationConsumption::query()
            ->where('user_id', $certificate->user_id)
            ->whereDate('consumed_at', now()->toDateString())
            ->whereHas('celebration_event', fn ($q) => $q->where('tier', 3))
            ->count();

        if ($todayPeaks >= (int) setting('celebrations.peak.daily_cap', 3)) {
            return false;
        }

        CelebrationConsumption::create([
            'user_id' => $certificate->user_id,
            'celebration_event_id' => $event->id,
            'reference_type' => $certificate->getMorphClass(),
            'reference_id' => $certificate->id,
            'consumed_at' => now(),
        ]);

        return true;
    }

    // ------------------------------------------------------------ الداخل

    private function existing(User $user, CertificateType $type, ?Model $subject): ?Certificate
    {
        return Certificate::query()
            ->where('user_id', $user->id)
            ->where('certificate_type_id', $type->id)
            ->when(
                $subject,
                fn ($q) => $q->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey()),
                fn ($q) => $q->whereNull('subject_id'),
            )
            ->where('status', 'valid')
            ->first();
    }

    /** التوقيع الرقميّ: يثبت أنّ البيانات المعروضة هي التي صدرت (12.5-هـ) */
    private function hash(string $code, User $user, CertificateType $type, string $issuedAt): string
    {
        return hash_hmac('sha256', implode('|', [$code, $user->id, $type->key, $issuedAt]), (string) config('app.key'));
    }

    /** تجميد نسخة القالب لحظة الإصدار (12.5-ج) */
    private function templateSnapshot(CertificateType $type, string $language): array
    {
        $template = CertificateTemplate::query()
            ->where('certificate_type_id', $type->id)
            ->where('language', $language)
            ->orderByDesc('is_default')
            ->orderByDesc('version')
            ->first();

        $accreditation = $type->accreditation;

        return [
            'template_id' => $template?->id,
            'template_version' => $template?->version ?? 1,
            'language' => $language,
            'width_px' => $template?->width_px ?? (int) setting('certificates.render.default_width_px', 1754),
            'height_px' => $template?->height_px ?? (int) setting('certificates.render.default_height_px', 1240),
            'background_path' => $template?->background_path,
            'layers' => $template?->layers ?? [],
            'accreditation' => [
                'name_ar' => $accreditation?->name_ar,
                'name_en' => $accreditation?->name_en,
                'logo_path' => $accreditation?->logo_path,
            ],
            'frozen_at' => now()->toIso8601String(),
        ];
    }

    private function dataSnapshot(User $user, CertificateType $type, ?Model $subject, array $data, string $code, $issuedAt): array
    {
        $subjectName = $data['certificate_name']
            ?? ($subject?->name_ar ?? $subject?->title_ar ?? $type->name_ar);

        return array_merge([
            'holder_name' => $user->name,
            'holder_code' => $user->code,
            'certificate_name' => $subjectName,
            'type_key' => $type->key,
            'type_name' => $type->name_ar,
            'accreditation_name' => $type->accreditation?->name_ar ?? (string) setting('certificates.accreditation.default_name', 'اعتماد المنصّة'),
            'accreditation_logo' => $type->accreditation?->logo_path,
            'country' => $user->country?->name_ar,
            'issued_on' => $issuedAt->format((string) setting('certificates.render.date_format', 'Y/m/d')),
            'code' => $code,
        ], $data);
    }
}
