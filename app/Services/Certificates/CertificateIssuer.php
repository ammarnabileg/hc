<?php

namespace App\Services\Certificates;

use App\Models\CelebrationConsumption;
use App\Models\CelebrationEvent;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Models\Country;
use App\Models\User;
use App\Services\Onboarding\HolderIdentity;
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
        private readonly CertificateSignature $signature,
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
                'user_id' => $user->id,
                'certificate_type_id' => $type->id,
                'language' => $language,
                'template_snapshot' => $this->templateSnapshot($type, $language),
                'data_snapshot' => $this->dataSnapshot($user, $type, $subject, $data, $code, $issuedAt, $language),
                'source' => $source,
                'issued_at' => $issuedAt,
                'status' => 'valid',
                'issued_by' => $issuedBy?->id,
            ]);

            if ($subject) {
                $certificate->subject()->associate($subject);
            }

            /*
             | ⭐ التوقيع **بعد** اكتمال اللقطتين لا قبلهما (12.5-هـ): التوقيع يغطّي
             | ما يُعرَض على المتحقِّق، فلا يُشتقّ إلّا ممّا استقرّ فعلًا في الصفّ.
             | ومصدره واحدٌ لكلّ المنصّة — `CertificateSignature` — فلا نسخةَ ثانية
             | تنحرف عن الأولى فتُعلَن شهادةٌ صحيحةٌ مزوَّرة.
             */
            $certificate->setRelation('certificate_type', $type);
            $certificate->hash = $this->signature->for($certificate);

            $certificate->save();

            /*
             | ⭐ إبطال الكاش لحظة الإصدار (8 · 8.1): الكود قد يكون قد خدم شهادةً
             | سابقةً بنفس الرقم، فلو بقي ملفّها المرسوم أجاب الرابطَ الجديد
             | باسم صاحبها القديم — صفحة التحقّق تقول شيئًا والصورة تقول آخر.
             */
            $this->renderer->forget($certificate);

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

    private function dataSnapshot(User $user, CertificateType $type, ?Model $subject, array $data, string $code, $issuedAt, string $language = 'ar'): array
    {
        $subjectName = $data['certificate_name'] ?? $this->certificateName($subject, $type, $language);

        return array_merge([
            /*
             | ⭐ اسم الشهادة من **بيانات الشهادات والإفادات** (2.5-ج): الاسم بلغة
             | النسخة ومعه اللقب. واللقطة مجمَّدة لحظة الإصدار (12.5-ج)، فلو خرجت
             | باسمٍ ناقص لا تُصلَح بعدها — ولذلك تُقرأ الحقول من مصدرها الواحد.
             */
            ...HolderIdentity::documentFields($user, $language),
            'holder_code' => $user->code,
            'certificate_name' => $subjectName,
            'type_key' => $type->key,
            'type_name' => $type->name_ar,
            'accreditation_name' => $type->accreditation?->name_ar ?? (string) setting('certificates.accreditation.default_name', 'اعتماد المنصّة'),
            'accreditation_logo' => $type->accreditation?->logo_path,
            'country' => $this->holderCountry($user, $language),
            'issued_on' => $issuedAt->format((string) setting('certificates.render.date_format', 'Y/m/d')),
            'code' => $code,
        ], $data);
    }

    /**
     * ⭐ **اسم الشهادة لا اسم العرض** (8 · 3).
     *
     * لكلّ تدريب اسمان: اسم عرضٍ يراه الناس على المنصّة، واسم شهادةٍ يُكتَب على
     * الوثيقة — كلٌّ بالعربيّ والإنجليزيّ. والدستور صريح: «اسم التدريب (**اسم
     * الشهادة** — مش اسم العرض)». فنقرأ `cert_name_*` أوّلًا، ولا نرتدّ إلى اسم
     * العرض إلّا حين لا يكون للكيان اسم شهادةٍ أصلًا (فعاليّة أو بوزشن مثلًا).
     */
    private function certificateName(?Model $subject, CertificateType $type, string $language): string
    {
        $ordered = $language === 'en'
            ? ['cert_name_en', 'cert_name_ar', 'name_en', 'name_ar', 'title_en', 'title_ar']
            : ['cert_name_ar', 'cert_name_en', 'name_ar', 'name_en', 'title_ar', 'title_en'];

        foreach ($subject ? $ordered : [] as $attribute) {
            $value = trim((string) ($subject->getAttribute($attribute) ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return (string) ($language === 'en' ? ($type->name_en ?: $type->name_ar) : $type->name_ar);
    }

    /**
     * الدولة **من ملفّ المستخدم لحظة الإصدار** (8): الدستور يعدّها من بيانات
     * الشهادة، وكانت تخرج فارغةً كلّما لم يكن الكشف قد جرى بعد. فإن غابت
     * صراحةً نستنتجها من منطقته الزمنيّة ونثبّتها في ملفّه — فتُصلَح مرّةً
     * لكلّ وثائقه بدل أن تُترَك فارغةً في لقطةٍ لا تُصلَح بعد تجميدها.
     */
    private function holderCountry(User $user, string $language): ?string
    {
        $user->loadMissing('country');

        if (! $user->country && ! $user->country_locked_at) {
            $timezone = $user->timezone ?: $user->auto_timezone;

            $country = $timezone
                ? Country::query()->where('timezone', $timezone)->where('is_active', true)->first()
                : null;

            if ($country) {
                $user->forceFill(['country_id' => $country->id])->saveQuietly();
                $user->setRelation('country', $country);
            }
        }

        $country = $user->country;

        if (! $country) {
            return null;
        }

        return $language === 'en'
            ? (string) ($country->name_en ?: $country->name_ar)
            : (string) ($country->name_ar ?: $country->name_en);
    }
}
