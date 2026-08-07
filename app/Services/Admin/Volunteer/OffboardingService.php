<?php

namespace App\Services\Admin\Volunteer;

use App\Models\ConsentRequest;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Offboarding;
use App\Models\Reentry;
use App\Models\User;
use App\Models\VolunteerCard;
use App\Services\Volunteer\Org\PromotionLadder;
use App\Services\Volunteer\People\PositionRoleAssigner;
use App\Services\Volunteer\Retention\SuspensionService;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * الأوفبوردنج (13.4-س) — ثلاثة أنواع لا رابع لها:
 *  استقالة طوعيّة · انتهاء كيان مؤقّت · **إقصاء حصرًا عبر سلّم العتبات**.
 *
 * ⛔ «إنهاء إداريّ» و«خمول» ليسا نوعَي خروج — الخمول مدخل للسلّم لا مسار خروج،
 * فلا يُفتَح بابٌ موازٍ للطرد بمزاج الأبلاين.
 */
class OffboardingService
{
    /**
     * حالات العضويّة التي **يُنهيها** الخروج — النشِطة **والمعلَّقة** معًا.
     * (والمنتهية سلفًا لا تُمَسّ: مَن بُتِرت اختياريّاته عند −9.5 تبقى بتاريخها.)
     */
    public const CLOSABLE_STATUSES = ['active', SuspensionService::MEMBERSHIP_STATUS];

    /** أنواع الخروج الثلاثة — مفاتيح داخليّة لا نصّ (2.13-ب) */
    public const TYPE_KEYS = ['resignation', 'entity_ended', 'exclusion'];

    /**
     * عناوين أنواع الخروج — من `setting()` لا محروقة (2.13).
     *
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            'resignation' => (string) setting('volunteer.offboarding.type.resignation', 'استقالة طوعيّة'),
            'entity_ended' => (string) setting('volunteer.offboarding.type.entity_ended', 'انتهاء كيان مؤقّت (ملفّ)'),
            'exclusion' => (string) setting('volunteer.offboarding.type.exclusion', 'إقصاء (عبر سلّم العتبات وحده)'),
        ];
    }

    /** بنود التصفية الإلزاميّة — لا إنهاء قبل اكتمالها */
    public static function clearanceItems(): array
    {
        $items = setting('volunteer.offboarding.clearance_items', []);

        return is_array($items) ? $items : [];
    }

    /** قائمة الأسباب المقنَّنة (§س-ي) — يختار منها الأدمن لا يكتب نصًّا حرًّا */
    public static function reasons(): array
    {
        $reasons = setting('volunteer.offboarding.reasons', []);

        return is_array($reasons) ? $reasons : [];
    }

    /** هل بلغ المتطوّع عتبة الإقصاء؟ الإقصاء لا يُفتَح إلّا بها */
    public static function reachedExclusionThreshold(User $user): bool
    {
        $rep = Integrations::balance($user, BehaviorLedger::REP);

        return $rep <= rep_rule('limit.suspension', -10);
    }

    /** مدّة التبريد المحسوبة حسب نوع الخروج */
    public static function cooldownUntil(string $type): ?Carbon
    {
        return match ($type) {
            'resignation' => now()->addDays((int) setting('volunteer.offboarding.cooldown_days.resignation', 30)),
            'entity_ended' => now()->addDays((int) setting('volunteer.offboarding.cooldown_days.entity_ended', 30)),
            // الإقصاء = لا عودة إلّا بقرار مشرف عام التطوّع
            'exclusion' => (bool) setting('volunteer.offboarding.exclusion_allows_return', false)
                ? now()->addDays((int) setting('volunteer.offboarding.cooldown_days.thresholds', 90))
                : null,
            default => null,
        };
    }

    /**
     * فتح ملفّ إنهاء عضويّة.
     *
     * @param  array<int,bool>  $checklist  حالة بنود التصفية
     * @param  ?Entity  $entity  ⭐ لـ«انتهاء كيان مؤقّت (ملفّ)» وحده — يحصر `complete()` إغلاق
     *                           العضويّات بهذا الكيان دون سواه (23-0.2 · §1518 · §3813)، فمَن
     *                           له عضويّة قسمٍ أخرى لا تُقفَل ظلمًا لمجرّد انتهاء ملفّه. وما عداه
     *                           (استقالة/إقصاء) يبقى بلا تحديد — السلوك القديم: كلّ العضويّات.
     *
     * @throws RuntimeException عند نوعٍ غير مسموح أو إقصاءٍ بلا عتبة
     */
    public static function open(User $target, string $type, ?string $reason, User $actor, array $checklist = [], ?Entity $entity = null): Offboarding
    {
        if (! in_array($type, self::TYPE_KEYS, true)) {
            throw new RuntimeException(setting('volunteer_offboarding.offboarding_service.open_1', 'نوع الخروج غير معروف — الأنواع ثلاثة لا رابع لها.'));
        }

        // ⭐ الإقصاء حصرًا عبر سلّم العتبات — لا فصل بقرار فرديّ
        if ($type === 'exclusion' && ! self::reachedExclusionThreshold($target)) {
            throw new RuntimeException(setting('volunteer_offboarding.offboarding_service.open_2', 'الإقصاء لا يكون إلّا عبر سلّم العتبات — درجة الالتزام لم تبلغ عتبة التعليق بعد.'));
        }

        $noticeDays = (int) setting('volunteer.offboarding.notice_days', 7);

        $record = Offboarding::create([
            'user_id' => $target->id,
            'entity_id' => $entity?->id,
            'type' => $type,
            // ⭐ السبب لا يُنشَر للفريق — يبقى في الملاحظات الإداريّة وحدها
            'reason' => $reason,
            'initiated_by' => $actor->id,
            // مهلة الإشعار تُصفَّر في الإقصاء
            'notice_until' => $type === 'exclusion' ? now() : now()->addDays($noticeDays),
            'clearance_checklist' => self::normalizeChecklist($checklist),
            'cooldown_until' => self::cooldownUntil($type),
        ]);

        AuditTrail::log($actor, 'offboarding.open', $record, [], ['type' => $type, 'user_id' => $target->id]);

        return $record;
    }

    /** هل اكتملت التصفية؟ */
    public static function clearanceComplete(Offboarding $record): bool
    {
        $checklist = $record->clearance_checklist ?? [];

        if (! $checklist) {
            return false;
        }

        foreach ($checklist as $row) {
            if (empty($row['done'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * إتمام الإنهاء: يقفل العضويّات ويُصدر شهادة الخروج المشرَّف عند استحقاقها.
     *
     * ⭐ ويُنفِّذ **أثر الخروج كاملًا** لا شكليًّا (13.4-س-ز · ط):
     *  1) **إلغاء تلقائيّ لكلّ موافقات إظهار التواصل** — ما منحه وما مُنِح له.
     *     كان الخارج يظلّ رقمه وبريده مكشوفَين لزملاء لم تعد بينهم علاقة، وهو
     *     **خرق خصوصيّة فعليّ**.
     *  2) **شهادة خبرة التطوّع فعلًا** — كان العلَم يقول «صدرت» والسجلّ خالٍ.
     *  3) **البطاقة الرقميّة تصير «منتهية» لحظة الخروج** لا كسولًا عند أوّل زيارة.
     *
     * @throws RuntimeException إن لم تكتمل التصفية الإلزاميّة
     */
    public static function complete(Offboarding $record, User $actor): Offboarding
    {
        if (! self::clearanceComplete($record)) {
            throw new RuntimeException(setting('volunteer_offboarding.offboarding_service.complete_1', 'التصفية الإلزاميّة لم تكتمل — كمّل بنود التشيك-ليست قبل الإنهاء.'));
        }

        /*
         | ⭐ **والمعلَّقة تُنهى كما تُنهى النشِطة** (23-0.2-4): «تنتقل مسؤوليّاته
         | الإشرافيّة … ويعود التفويض تلقائيًّا عند إعادة التفعيل، أو **يتحوّل
         | شغورًا حقيقيًّا (سلّم الترقية) عند قرار الإقصاء**».
         |
         | وهذا ليس تفصيلًا: **الإقصاء لا يقع إلّا على معلَّق**. فالسلّم يوجب
         | التعليق عند −10 قبل أيّ إنهاء («التعليق **قبل أيّ إنهاء**»)، والإقصاء
         | يُرفَض لمن لم يبلغ العتبة (13.4-س-أ). فلو اقتصر الإنهاء على `active`
         | لَخرج المُقصى وعضويّاته `suspended` **إلى الأبد**: دورُه في يده،
         | وحلقتُه قائمة في سلسلة التصعيد، ولا شغور يُملأ — أي أنّ الإقصاء يصير
         | إجراءً بلا أثر على مَن هو وحده أهلٌ له.
         */
        /*
         | ⭐ **انتهاء كيان مؤقّت (ملفّ) يحصر الإغلاق بكيانه وحده** (23-0.2 ·
         | §1518 · §3813): «إنهاء الملفّ فتُقفَل عضويّاته تلقائيًّا» — عضويّاته
         | هو لا كلّ عضويّات صاحبها. فمَن له عضويّة قسمٍ أخرى معه لا تُقفَل
         | ظلمًا لمجرّد أنّ ملفًّا شارك فيه انتهى. أمّا الاستقالة والإقصاء
         | فيبقيان بلا تحديدٍ — السلوك القديم: كلّ العضويّات (`entity_id` هنا).
         */
        $closing = Membership::query()
            ->where('user_id', $record->user_id)
            ->whereIn('status', self::CLOSABLE_STATUSES)
            ->when($record->entity_id, fn ($q) => $q->where('entity_id', $record->entity_id))
            ->get();

        Membership::query()
            ->where('user_id', $record->user_id)
            ->whereIn('status', self::CLOSABLE_STATUSES)
            ->when($record->entity_id, fn ($q) => $q->where('entity_id', $record->entity_id))
            ->update(['status' => 'ended', 'ended_at' => now(), 'end_reason' => $record->type]);

        /*
         | ⭐ إفراج التعليق حسابٌ كامل — لا يجوز أن يفكّه إغلاق ملفٍّ واحد فقط.
         | فتعليق الحساب أثرٌ على المستخدم كلّه (12.2.1)، ولو فُكّ لمجرّد إغلاق
         | كيانٍ مؤقّت واحد لعاد المعلَّق نشِطًا بقيّة عضويّاته بلا قرار لجنة.
         */
        if ($record->user && ! $record->entity_id) {
            app(SuspensionService::class)->release(
                $record->user,
                $actor,
                SuspensionService::RELEASE_DISMISSAL,
                $record->type,
            );
        }

        /*
         | ⭐ **وينتهي الدور بانتهاء العضويّة** (13.4-س · 12.2.3-ب): التسكين يمنح
         | دور البوزشن، فالخروج يسحبه — وإلّا بقي المُقصى يحمل صلاحيّات لم تعد له.
         | والسحب **بالعضويّة** لا بالمستخدم، فأدوار المنصّة لا يمسّها شيء، ومَن
         | له عضويّتان لا تُجرَّد إحداهما بإنهاء الأخرى.
         */
        $assigner = app(PositionRoleAssigner::class);

        foreach ($closing as $membership) {
            $assigner->revoke($membership);
        }

        /*
         | ⭐ **لا فترة شغور أصلًا** (القسم 0 · 23-0.2): كلّ عضويّةٍ أُغلِقت للتوّ
         | قد تكون بوزشنًا شاغرًا الآن — وسلّم الترقية يملؤه فورًا من داونلاينه
         | المباشر، أو يعيّن «قائم بأعمال» إن كان دايركتورًا بانتظار الاعتماد.
         */
        $ladder = app(PromotionLadder::class);

        foreach ($closing as $membership) {
            $ladder->fillVacancy($membership, $actor);
        }

        $user = $record->user;

        // خروج مُشرَّف: شهادة خبرة في الاستقالة وانتهاء الملفّ فقط — لا في الإقصاء
        $honorable = $record->type !== 'exclusion'
            && (bool) setting('volunteer.offboarding.honorable_certificate_enabled', true);

        $issued = false;

        if ($honorable && $user) {
            $issued = CertificateEligibility::issueExperience($user, $actor)['issued'];
        }

        // ⭐ شهادة «مشاركة في ملفّ» (13.4-ع-3) — لكلّ عضويّة كيانٍ مؤقّت أُغلِقت هنا فعلًا
        $caseFileCertificates = 0;

        if ($record->type === 'entity_ended') {
            foreach ($closing as $membership) {
                if (CertificateEligibility::issueCaseFile($membership, $actor)['issued']) {
                    $caseFileCertificates++;
                }
            }
        }

        /*
         | ⭐ **الآثار الحسابيّة الكاملة (موافقات التواصل · انتهاء البطاقة) تخصّ
         | خروجًا كاملًا لا إغلاق كيانٍ واحد** — فمَن له عضويّة قسمٍ نشِطة معه لا
         | تُسحَب موافقاته ولا تنتهي بطاقته لمجرّد أنّ ملفًّا شارك فيه انتهى.
         */
        $revoked = $user && ! $record->entity_id ? self::revokeContactConsents($user) : 0;
        $expiredCards = $user && ! $record->entity_id ? self::expireCards($user) : 0;

        $record->forceFill([
            'approved_by' => $actor->id,
            'completed_at' => now(),
            // العلَم يقول ما حدث فعلًا لا ما كان مقصودًا
            'honorable_certificate_issued' => $issued,
        ])->save();

        if ($user) {
            // الرسالة للفريق بلا سبب — و«انتهى الملفّ» تختلف عن «انتهت عضويّتك» كاملةً
            Integrations::notify(
                $user, 'account',
                $record->entity_id
                    ? (string) setting('volunteer_offboarding.offboarding_service.complete_3', 'انتهى ملفّك التطوّعيّ')
                    : setting('volunteer_offboarding.offboarding_service.complete_2', 'انتهت عضويّتك التطوّعيّة'),
                str_replace('{name}', $user->name, (string) setting('volunteer.offboarding.team_message', '')),
                null, 'platform',
            );
        }

        AuditTrail::log($actor, 'offboarding.complete', $record, [], [
            'honorable' => $honorable,
            'experience_certificate_issued' => $issued,
            'case_file_certificates_issued' => $caseFileCertificates,
            'consents_revoked' => $revoked,
            'cards_expired' => $expiredCards,
        ]);

        return $record;
    }

    /**
     * ⭐ **إلغاء تلقائيّ لكلّ موافقات إظهار التواصل** (13.4-س-ز) — الاتّجاهين معًا:
     * ما منحه الخارج لغيره، وما مُنِح له. وبلا إشعار لأيّ طرف، على نفس فلسفة
     * السحب الصامت (13.4-م-2).
     *
     * @return int عدد الموافقات التي أُلغيت
     */
    public static function revokeContactConsents(User $user): int
    {
        return ConsentRequest::query()
            ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhere('requester_id', $user->id))
            ->whereIn('status', ['granted', 'pending'])
            ->update(['status' => 'revoked', 'revoked_at' => now()]);
    }

    /**
     * ⭐ البطاقة تصير **«منتهية» لحظة الخروج** ولا تُحذَف (13.4-ر-ج) — وتبقى في
     * سجلّه بتاريخيها. والحساب الكسول عند أوّل زيارة كان يترك بطاقةً «سارية»
     * على الويب لمن انتهت عضويّته.
     *
     * @return int عدد البطاقات التي انتهت
     */
    public static function expireCards(User $user): int
    {
        return VolunteerCard::query()
            ->where('user_id', $user->id)
            ->where('status', 'valid')
            ->update(['status' => 'expired', 'expired_at' => now()]);
    }

    /** حالة العودة: متاحة؟ ومتى؟ (13.4-ق) */
    public static function reentryState(Offboarding $record): array
    {
        if ($record->type === 'exclusion' && ! (bool) setting('volunteer.offboarding.exclusion_allows_return', false)) {
            return [
                'state' => 'danger',
                'label' => setting('volunteer_offboarding.offboarding_service.reentry_state_1', 'لا عودة إلّا بقرار مشرف عام التطوّع'),
                'copy' => (string) setting('volunteer.offboarding.excluded_copy', ''),
            ];
        }

        $until = $record->cooldown_until;

        if ($until && $until->isFuture()) {
            return [
                'state' => 'warn',
                'label' => strtr(setting('volunteer_offboarding.offboarding_service.reentry_state_2', 'داخل التبريد حتى :p1'), [':p1' => (string) ($until->format('Y-m-d'))]),
                'copy' => str_replace('{date}', $until->format('Y-m-d'), (string) setting('volunteer.offboarding.cooldown_copy', '')),
            ];
        }

        return ['state' => 'ok', 'label' => setting('volunteer_offboarding.offboarding_service.reentry_state_3', 'العودة متاحة'), 'copy' => ''];
    }

    /** فتح ملفّ عودة — الامتحان إجباريّ والشهادة القديمة تصير «منتهية» */
    public static function openReentry(User $user, ?Offboarding $record, User $actor): Reentry
    {
        $reentry = Reentry::create([
            'user_id' => $user->id,
            'offboarding_id' => $record?->id,
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        AuditTrail::log($actor, 'reentry.open', $reentry, [], [
            'exam_required' => (bool) setting('volunteer.offboarding.reentry_exam_required', true),
            'starts_position' => (string) setting('volunteer.offboarding.reentry_starts_position', 'coordinator'),
        ]);

        return $reentry;
    }

    private static function normalizeChecklist(array $checked): array
    {
        $out = [];

        foreach (self::clearanceItems() as $index => $label) {
            $out[] = ['label' => $label, 'done' => (bool) ($checked[$index] ?? false)];
        }

        return $out;
    }
}
